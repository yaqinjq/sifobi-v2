<?php

namespace App\Http\Controllers\Procurement;

use App\Http\Controllers\Controller;
use App\Modules\Core\Models\Department;
use App\Modules\Core\Models\Outlet;
use App\Modules\Inventory\Models\Item;
use App\Modules\Inventory\Models\Unit;
use App\Modules\Procurement\Models\PurchaseOrder;
use App\Modules\Procurement\Models\PurchaseOrderItem;
use App\Services\PurchaseOrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PurchaseOrderController extends Controller
{
    public function __construct(private readonly PurchaseOrderService $service) {}

    public function index(Request $request): View
    {
        $tenantId = $this->tenantId($request);

        $pos = PurchaseOrder::query()
            ->where('tenant_id', $tenantId)
            ->when($request->user()->outlet_id, fn ($q) => $q->where('outlet_id', $request->user()->outlet_id))
            ->with(['outlet', 'department', 'requestedBy'])
            ->withCount('items')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->upper()->toString()))
            ->when($request->filled('type'), fn ($q) => $q->where('po_type', $request->string('type')->upper()->toString()))
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('procurement.purchase-orders.index', compact('pos'));
    }

    public function create(Request $request): View
    {
        $tenantId = $this->tenantId($request);

        $outlets = Outlet::query()
            ->where('tenant_id', $tenantId)
            ->where('status', 'ACTIVE')
            ->when($request->user()->outlet_id, fn ($q) => $q->where('id', $request->user()->outlet_id))
            ->orderBy('name')
            ->get();

        $departments = Department::query()
            ->where('tenant_id', $tenantId)
            ->where('status', 'ACTIVE')
            ->orderBy('name')
            ->get();

        $types = PurchaseOrder::TYPE_LABELS_ACTIVE;

        return view('procurement.purchase-orders.create', compact('outlets', 'departments', 'types'));
    }

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
            'requestedBy',
            'submittedBy',
            'approvedBy',
            'rejectedBy',
            'sentBy',
            'closedBy',
            'items.item.inventoryUnit',
            'items.unit',
            'approvalEvents.actor',
        ]);

        $items = collect();
        $units = collect();

        if ($purchaseOrder->canEdit()) {
            $items = Item::query()
                ->where('tenant_id', $tenantId)
                ->where('is_active', true)
                ->where('track_stock', true)
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

        $this->service->addItem($purchaseOrder, $tenantId, $validated);

        return redirect()
            ->route('procurement.purchase-orders.show', $purchaseOrder)
            ->with('success', 'Item berhasil ditambahkan.');
    }

    public function removeItem(Request $request, PurchaseOrder $purchaseOrder, PurchaseOrderItem $item): RedirectResponse
    {
        $tenantId = $this->tenantId($request);

        abort_unless((int) $purchaseOrder->tenant_id === $tenantId, 403);
        abort_unless((int) $item->purchase_order_id === (int) $purchaseOrder->id, 404);

        $this->service->removeItem($purchaseOrder, $item);

        return redirect()
            ->route('procurement.purchase-orders.show', $purchaseOrder)
            ->with('success', 'Item berhasil dihapus.');
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
