<?php

namespace App\Http\Controllers\Procurement;

use App\Http\Controllers\Concerns\HasBulkAction;
use App\Http\Controllers\Concerns\HasPerPageSelector;
use App\Http\Controllers\Controller;
use App\Http\Requests\Procurement\BulkApprovePurchaseOrderRequest;
use App\Modules\Core\Models\Department;
use App\Modules\Core\Models\Outlet;
use App\Modules\Inventory\Models\Item;
use App\Modules\Inventory\Models\Unit;
use App\Modules\Procurement\Models\PurchaseOrder;
use App\Modules\Procurement\Models\PurchaseOrderItem;
use App\Services\PurchaseOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PurchaseOrderController extends Controller
{
    use HasBulkAction;
    use HasPerPageSelector;

    public function __construct(private readonly PurchaseOrderService $service) {}

    public function index(Request $request): View
    {
        $tenantId = $this->tenantId($request);
        $tab      = $request->input('tab', 'all');
        [$perPage, $perPageOptions] = $this->perPageAndOptions($request, 20);

        $tabStatusMap = [
            'draft'     => PurchaseOrder::STATUS_DRAFT,
            'submitted' => PurchaseOrder::STATUS_SUBMITTED,
            'approved'  => PurchaseOrder::STATUS_APPROVED,
            'sent'      => PurchaseOrder::STATUS_SENT,
            'shipped'   => PurchaseOrder::STATUS_SHIPPED,
            'closed'    => PurchaseOrder::STATUS_CLOSED,
            'rejected'  => PurchaseOrder::STATUS_REJECTED,
        ];

        $user = $request->user();

        $baseQuery = PurchaseOrder::query()
            ->where('tenant_id', $tenantId)
            ->when($user->accessibleOutletIds(), fn ($q, $ids) => $q->whereIn('outlet_id', $ids))
            ->when(
                $user->department_id && ! $user->can('view_all_po'),
                fn ($q) => $q->where('department_id', $user->department_id)
            )
            ->when($request->filled('type'), fn ($q) => $q->where('po_type', $request->string('type')->upper()->toString()));

        $counts = (clone $baseQuery)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $pos = (clone $baseQuery)
            ->with(['outlet', 'department', 'requestedBy.roles', 'requestedBy.outlet'])
            ->withCount('items')
            ->when(isset($tabStatusMap[$tab]), fn ($q) => $q->where('status', $tabStatusMap[$tab]))
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();

        return view('procurement.purchase-orders.index', compact('pos', 'counts', 'tab', 'perPage', 'perPageOptions'));
    }

    public function create(Request $request): View
    {
        $tenantId  = $this->tenantId($request);
        $user      = $request->user();

        $outletIds = $user->accessibleOutletIds();
        $canChangeOutlet = count($outletIds) > 1;

        // 1 outlet (lama atau baru) -> tetap tampil apa adanya, tidak ada
        // perubahan pengalaman. >1 outlet -> kirim daftarnya supaya view
        // bisa tampilkan dropdown pilih outlet.
        $outlet = count($outletIds) === 1
            ? Outlet::query()->where('tenant_id', $tenantId)->find($outletIds[0])
            : null;
        $outlets = $canChangeOutlet
            ? Outlet::query()->where('tenant_id', $tenantId)->whereIn('id', $outletIds)->orderBy('name')->get()
            : collect();

        $department = $user->department_id
            ? Department::query()->where('tenant_id', $tenantId)->find($user->department_id)
            : null;

        $allowedTypes = $department
            ? $department->allowedPoTypes()
            : array_keys(PurchaseOrder::TYPE_LABELS_ACTIVE);

        $typeLabels = array_intersect_key(PurchaseOrder::TYPE_LABELS_ACTIVE, array_flip($allowedTypes));

        $today = now()->toDateString();

        return view('procurement.purchase-orders.create', compact(
            'outlet', 'outlets', 'canChangeOutlet', 'department', 'typeLabels', 'today'
        ));
    }

    public function storeBatch(Request $request): JsonResponse
    {
        $tenantId  = $this->tenantId($request);
        $user      = $request->user();
        $outletIds = $user->accessibleOutletIds();

        if ($outletIds === []) {
            return response()->json([
                'error' => 'Akun Anda belum ditugaskan ke outlet manapun, jadi Purchase Order tidak bisa dibuat. Hubungi admin untuk mengatur outlet di menu Manajemen User.',
            ], 422);
        }

        $rules = [
            'needed_at'             => ['required', 'date', 'after_or_equal:today'],
            'notes'                 => ['nullable', 'string', 'max:2000'],
            'tabs'                  => ['required', 'array', 'min:1'],
            'tabs.*'                => ['array'],
            'tabs.*.*.item_id'      => ['required', 'integer', Rule::exists('items', 'id')->where('tenant_id', $tenantId)],
            'tabs.*.*.unit_id'      => ['required', 'integer', Rule::exists('units', 'id')->where('tenant_id', $tenantId)],
            'tabs.*.*.qty_ordered'  => ['required', 'numeric', 'min:0.001'],
        ];

        // >1 outlet -> outlet_id WAJIB dikirim client, dan HARUS salah
        // satu dari outlet MILIK USER INI (Rule::in($outletIds) adalah
        // baris otorisasi-nya -- bukan sekadar "outlet mana pun ada di
        // tenant"). 1 outlet -> tetap otomatis seperti sebelumnya, tidak
        // ada perubahan untuk user biasa.
        if (count($outletIds) > 1) {
            $rules['outlet_id'] = ['required', 'integer', Rule::in($outletIds)];
        }

        // Tangkap manual (bukan biarkan auto-convert Laravel) -- route ini
        // bukan di bawah prefix api/*, dan bootstrap/app.php men-set
        // shouldRenderJsonWhen() supaya cuma rute api/* yang otomatis
        // dapat respons JSON saat validasi gagal; selain itu defaultnya
        // redirect HTML. Endpoint ini dipanggil AJAX (poForm() di
        // create.blade.php selalu memanggil res.json()), jadi WAJIB
        // tetap balas JSON eksplisit di sini -- bukan cuma utk outlet_id
        // yang baru ditambah, tapi utk SEMUA rule di atas.
        try {
            $data = $request->validate($rules);
        } catch (ValidationException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'errors' => $exception->errors(),
            ], 422);
        }

        $outletId = count($outletIds) > 1 ? (int) $data['outlet_id'] : $outletIds[0];

        $pos = $this->service->createBatch(
            array_merge($data, [
                'tenant_id'     => $tenantId,
                'outlet_id'     => $outletId,
                'department_id' => $user->department_id ? (int) $user->department_id : null,
            ]),
            (int) $user->id
        );

        if (empty($pos)) {
            return response()->json(['error' => 'Tidak ada item yang diisi di tab manapun.'], 422);
        }

        $ids = collect($pos)->pluck('id')->join(',');

        return response()->json([
            'redirect' => route('procurement.purchase-orders.batch-summary', ['ids' => $ids]),
        ]);
    }

    public function batchSummary(Request $request): View
    {
        $tenantId = $this->tenantId($request);

        $ids = array_filter(array_map('intval', explode(',', (string) $request->string('ids'))));

        $pos = PurchaseOrder::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('id', $ids)
            ->with(['outlet', 'department', 'items.item', 'items.unit'])
            ->get();

        abort_if($pos->isEmpty(), 404);

        return view('procurement.purchase-orders.batch-summary', compact('pos'));
    }

    /** @deprecated use storeBatch for new batch create flow */
    public function store(Request $request): RedirectResponse
    {
        $tenantId = $this->tenantId($request);

        $validated = $request->validate([
            'outlet_id'     => ['required', 'integer', Rule::exists('outlets', 'id')->where('tenant_id', $tenantId)],
            'department_id' => ['nullable', 'integer', Rule::exists('departments', 'id')->where('tenant_id', $tenantId)],
            'po_type'       => ['required', Rule::in(array_keys(PurchaseOrder::TYPE_LABELS_ACTIVE))],
            'needed_at'     => ['nullable', 'date'],
            'notes'         => ['nullable', 'string', 'max:2000'],
        ]);

        $po = $this->service->create(
            array_merge($validated, ['tenant_id' => $tenantId]),
            (int) $request->user()->id
        );

        return redirect()
            ->route('procurement.purchase-orders.show', $po)
            ->with('success', 'PO #'.$po->po_number.' berhasil dibuat. Tambahkan item sekarang.');
    }

    public function show(Request $request, PurchaseOrder $purchaseOrder): View
    {
        $tenantId = $this->tenantId($request);

        abort_unless((int) $purchaseOrder->tenant_id === $tenantId, 403);

        $purchaseOrder->load([
            'outlet',
            'department',
            'supplier',
            'requestedBy.roles',
            'requestedBy.outlet',
            'submittedBy',
            'approvedBy',
            'rejectedBy',
            'sentBy',
            'closedBy',
            'items.item.inventoryUnit',
            'items.unit',
            'approvalEvents.actor',
            'shipments.goodsReceipt',
            'goodsReceipts',
        ]);

        $items = collect();
        $units = collect();

        if ($purchaseOrder->canEdit($request->user())) {
            $items = Item::query()
                ->where('tenant_id', $tenantId)
                ->where('is_active', true)
                ->when(
                    $purchaseOrder->po_type === PurchaseOrder::TYPE_CENTRAL_KITCHEN,
                    fn ($q) => $q->where('item_source', 'WIPRO'),
                    fn ($q) => $purchaseOrder->po_type === PurchaseOrder::TYPE_OCIA_ROASTERY
                        ? $q->where('item_source', 'OCIA')
                        : $q->where('track_stock', true)->forPoType($purchaseOrder->po_type)
                )
                ->with(['inventoryUnit'])
                ->orderBy('name')
                ->get(['id', 'name', 'canonical_sku', 'inventory_unit_id']);

            $units = Unit::query()
                ->where('tenant_id', $tenantId)
                ->orderBy('name')
                ->get(['id', 'name', 'code']);
        }

        return view('procurement.purchase-orders.show', compact('purchaseOrder', 'items', 'units'));
    }

    public function addItem(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $tenantId = $this->tenantId($request);

        abort_unless((int) $purchaseOrder->tenant_id === $tenantId, 403);

        $validated = $request->validate([
            'item_id'     => ['required', 'integer', Rule::exists('items', 'id')->where('tenant_id', $tenantId)],
            'unit_id'     => ['required', 'integer', Rule::exists('units', 'id')->where('tenant_id', $tenantId)],
            'qty_ordered' => ['required', 'numeric', 'min:0.000001'],
            'unit_cost'   => ['nullable', 'numeric', 'min:0'],
            'notes'       => ['nullable', 'string', 'max:500'],
        ]);

        $this->service->addItem($purchaseOrder, $tenantId, $validated, $request->user());

        return redirect()
            ->route('procurement.purchase-orders.show', $purchaseOrder)
            ->with('success', 'Item berhasil ditambahkan.');
    }

    public function removeItem(Request $request, PurchaseOrder $purchaseOrder, PurchaseOrderItem $item): RedirectResponse
    {
        $tenantId = $this->tenantId($request);

        abort_unless((int) $purchaseOrder->tenant_id === $tenantId, 403);
        abort_unless((int) $item->purchase_order_id === (int) $purchaseOrder->id, 404);

        $this->service->removeItem($purchaseOrder, $item, $request->user());

        return redirect()
            ->route('procurement.purchase-orders.show', $purchaseOrder)
            ->with('success', 'Item berhasil dihapus.');
    }

    public function updateItem(Request $request, PurchaseOrder $purchaseOrder, PurchaseOrderItem $item): RedirectResponse
    {
        $tenantId = $this->tenantId($request);

        abort_unless((int) $purchaseOrder->tenant_id === $tenantId, 403);
        abort_unless((int) $item->purchase_order_id === (int) $purchaseOrder->id, 404);
        abort_unless($purchaseOrder->canEdit($request->user()), 403);

        $validated = $request->validate([
            'qty_ordered' => ['required', 'numeric', 'min:0.001'],
        ]);

        $item->update(['qty_ordered' => $validated['qty_ordered']]);

        return redirect()
            ->route('procurement.purchase-orders.show', $purchaseOrder)
            ->with('success', 'Jumlah item berhasil diubah.');
    }

    public function submit(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        abort_unless((int) $purchaseOrder->tenant_id === $this->tenantId($request), 403);

        $this->service->submit($purchaseOrder, (int) $request->user()->id);

        return redirect()
            ->route('procurement.purchase-orders.show', $purchaseOrder)
            ->with('success', 'PO berhasil diajukan untuk persetujuan.');
    }

    public function approve(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        abort_unless((int) $purchaseOrder->tenant_id === $this->tenantId($request), 403);

        $this->service->approve($purchaseOrder, (int) $request->user()->id);

        return redirect()
            ->route('procurement.purchase-orders.show', $purchaseOrder)
            ->with('success', 'PO berhasil disetujui.');
    }

    public function bulkApprove(BulkApprovePurchaseOrderRequest $request): RedirectResponse
    {
        $tenantId = $this->tenantId($request);
        $userId = (int) $request->user()->id;

        $purchaseOrders = PurchaseOrder::query()
            ->whereIn('id', $request->input('ids', []))
            ->where('tenant_id', $tenantId)
            ->where('status', PurchaseOrder::STATUS_SUBMITTED)
            ->get();

        $result = $this->runBulkAction(
            $purchaseOrders,
            fn (PurchaseOrder $po) => $this->service->approve($po, $userId),
            fn (PurchaseOrder $po) => $po->po_number ?? "#{$po->id}"
        );

        return $this->bulkActionRedirect(
            'procurement.purchase-orders.index',
            $result,
            "{$result['processed']} PO berhasil disetujui."
        );
    }

    public function reject(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        abort_unless((int) $purchaseOrder->tenant_id === $this->tenantId($request), 403);

        $request->validate([
            'rejection_notes' => ['required', 'string', 'min:5', 'max:2000'],
        ]);

        $this->service->reject($purchaseOrder, (int) $request->user()->id, $request->string('rejection_notes')->toString());

        return redirect()
            ->route('procurement.purchase-orders.show', $purchaseOrder)
            ->with('success', 'PO ditolak.');
    }

    public function send(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        abort_unless((int) $purchaseOrder->tenant_id === $this->tenantId($request), 403);

        $this->service->send($purchaseOrder, (int) $request->user()->id);

        return redirect()
            ->route('procurement.purchase-orders.show', $purchaseOrder)
            ->with('success', 'PO berhasil ditandai sebagai terkirim ke vendor.');
    }

    public function close(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        abort_unless((int) $purchaseOrder->tenant_id === $this->tenantId($request), 403);

        $this->service->close($purchaseOrder, (int) $request->user()->id);

        return redirect()
            ->route('procurement.purchase-orders.show', $purchaseOrder)
            ->with('success', 'PO ditandai selesai.');
    }

    public function resend(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        abort_unless((int) $purchaseOrder->tenant_id === $this->tenantId($request), 403);

        try {
            $this->service->resend($purchaseOrder);
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors());
        }

        $purchaseOrder->refresh();

        $target = $purchaseOrder->po_type === PurchaseOrder::TYPE_CENTRAL_KITCHEN ? 'Wipro' : 'OCIA';

        // Wipro pushes run in a queue worker (see PushOrderToWiproJob), not
        // synchronously, so the outcome isn't known yet at this point — OCIA
        // still pushes synchronously and its result is available immediately.
        if ($purchaseOrder->po_type === PurchaseOrder::TYPE_CENTRAL_KITCHEN) {
            return redirect()
                ->route('procurement.purchase-orders.show', $purchaseOrder)
                ->with('success', "Kirim ulang ke {$target} sedang diproses, cek status dalam beberapa saat.");
        }

        return redirect()
            ->route('procurement.purchase-orders.show', $purchaseOrder)
            ->with(
                $purchaseOrder->external_sync_error ? 'error' : 'success',
                $purchaseOrder->external_sync_error
                    ? "Kirim ulang ke {$target} gagal: ".$purchaseOrder->external_sync_error
                    : "PO berhasil dikirim ulang dan diterima {$target}."
            );
    }

    private function tenantId(Request $request): int
    {
        $tenantId = $request->user()?->tenant_id;

        abort_unless($tenantId, 403, 'Tenant belum terpasang pada user.');

        return (int) $tenantId;
    }
}
