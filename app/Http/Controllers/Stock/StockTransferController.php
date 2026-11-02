<?php

namespace App\Http\Controllers\Stock;

use App\Http\Controllers\Concerns\HasBulkAction;
use App\Http\Controllers\Concerns\HasPerPageSelector;
use App\Http\Controllers\Controller;
use App\Http\Requests\Stock\BulkSubmitStockTransferRequest;
use App\Modules\Core\Models\Outlet;
use App\Modules\Inventory\Models\Item;
use App\Modules\Stock\Models\StockTransfer;
use App\Modules\Stock\Models\StockTransferItem;
use App\Services\StockTransferService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class StockTransferController extends Controller
{
    use HasBulkAction;
    use HasPerPageSelector;

    public function __construct(private readonly StockTransferService $service) {}

    public function index(Request $request): View
    {
        $tenantId  = $this->tenantId($request);
        [$perPage, $perPageOptions] = $this->perPageAndOptions($request, 20);

        $transfers = StockTransfer::query()
            ->where('tenant_id', $tenantId)
            ->when($request->user()->accessibleOutletIds(), fn ($q, $ids) => $q->where(
                fn ($inner) => $inner->whereIn('from_outlet_id', $ids)
                                     ->orWhereIn('to_outlet_id', $ids)
            ))
            ->with(['fromOutlet', 'toOutlet', 'createdBy'])
            ->withCount('items')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->upper()->toString()))
            ->latest('transfer_date')
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();

        return view('operations.stock-transfers.index', compact('transfers', 'perPage', 'perPageOptions'));
    }

    public function create(Request $request): View
    {
        $tenantId = $this->tenantId($request);

        $outlets = Outlet::query()
            ->where('tenant_id', $tenantId)
            ->where('status', 'ACTIVE')
            ->when($request->user()->accessibleOutletIds(), fn ($q, $ids) => $q->whereIn('id', $ids))
            ->orderBy('name')
            ->get();

        return view('operations.stock-transfers.create', compact('outlets'));
    }

    public function store(Request $request): RedirectResponse
    {
        $tenantId = $this->tenantId($request);
        $outletIds = $request->user()->accessibleOutletIds();

        // Lanjutan akses multi-outlet (lihat b131d21 utk pola yang sama di
        // Goods Receipt) -- sebelumnya from/to outlet_id cuma divalidasi
        // "ada di tenant", tidak pernah dibatasi ke outlet milik user
        // (konvensi PERMISIF -- transfer MEMANG wajar dipakai antar 2
        // outlet milik user yang sama, beda dari Spoil & Waste yang
        // fail-closed). Global (0 outlet) tetap bebas seperti sebelumnya.
        $outletRule = $outletIds === [] ? Rule::exists('outlets', 'id')->where('tenant_id', $tenantId) : Rule::in($outletIds);

        $validated = $request->validate([
            'from_outlet_id'  => ['required', 'integer', $outletRule],
            'to_outlet_id'    => ['required', 'integer', $outletRule],
            'transfer_date'   => ['required', 'date'],
            'notes'           => ['nullable', 'string', 'max:2000'],
            'items'           => ['required', 'array', 'min:1'],
            'items.*.item_id' => ['required', 'integer', Rule::exists('items', 'id')->where('tenant_id', $tenantId)],
            'items.*.qty'     => ['required', 'numeric', 'min:0.000001'],
        ]);

        if ((int) $validated['from_outlet_id'] === (int) $validated['to_outlet_id']) {
            throw ValidationException::withMessages(['to_outlet_id' => 'Outlet asal dan tujuan tidak boleh sama.']);
        }

        DB::transaction(function () use ($validated, $tenantId, $request): void {
            $transfer = $this->service->create(array_merge($validated, ['tenant_id' => $tenantId]), (int) $request->user()->id);

            foreach ($validated['items'] as $itemData) {
                $item  = Item::query()->where('tenant_id', $tenantId)->findOrFail((int) $itemData['item_id']);
                $ratio = max(1.0, (float) ($item->inventory_ratio ?? 1));
                $qty   = (float) $itemData['qty'];

                StockTransferItem::query()->create([
                    'stock_transfer_id' => $transfer->id,
                    'item_id'           => $item->id,
                    'unit_id'           => $item->inventory_unit_id,
                    'qty'               => $qty,
                    'qty_in_base_unit'  => $qty * $ratio,
                ]);
            }
        });

        return redirect()->route('operations.stock-transfers.index')
            ->with('success', 'Transfer stok berhasil dibuat.');
    }

    public function show(Request $request, StockTransfer $transfer): View
    {
        $transfer->load(['fromOutlet', 'toOutlet', 'createdBy', 'submittedBy', 'approvedBy', 'rejectedBy', 'voidedBy', 'items.item.inventoryUnit', 'items.item.baseUnit', 'items.unit']);

        $userOutletIds = $request->user()->accessibleOutletIds();
        abort_if(
            $userOutletIds !== []
                && ! in_array((int) $transfer->from_outlet_id, $userOutletIds, true)
                && ! in_array((int) $transfer->to_outlet_id, $userOutletIds, true),
            403
        );

        return view('operations.stock-transfers.show', compact('transfer'));
    }

    public function submit(Request $request, StockTransfer $transfer): RedirectResponse
    {
        try {
            $this->service->submit($transfer, (int) $request->user()->id);
        } catch (ValidationException $e) {
            throw $e;
        }

        return redirect()->route('operations.stock-transfers.show', $transfer)
            ->with('success', 'Transfer stok berhasil disubmit untuk approval.');
    }

    public function bulkSubmit(BulkSubmitStockTransferRequest $request): RedirectResponse
    {
        $tenantId = $this->tenantId($request);
        $userId = (int) $request->user()->id;

        $transfers = StockTransfer::query()
            ->whereIn('id', $request->input('ids', []))
            ->where('tenant_id', $tenantId)
            ->where('status', StockTransfer::STATUS_DRAFT)
            ->get();

        $result = $this->runBulkAction(
            $transfers,
            fn (StockTransfer $transfer) => $this->service->submit($transfer, $userId),
            fn (StockTransfer $transfer) => "#{$transfer->id}"
        );

        return $this->bulkActionRedirect(
            'operations.stock-transfers.index',
            $result,
            "{$result['processed']} transfer stok berhasil disubmit."
        );
    }

    public function approve(Request $request, StockTransfer $transfer): RedirectResponse
    {
        try {
            $this->service->approve($transfer, (int) $request->user()->id);
        } catch (ValidationException $e) {
            throw $e;
        }

        return redirect()->route('operations.stock-transfers.show', $transfer)
            ->with('success', 'Transfer stok berhasil disetujui dan stok telah dipindahkan.');
    }

    public function reject(Request $request, StockTransfer $transfer): RedirectResponse
    {
        $request->validate([
            'rejection_reason' => ['required', 'string', 'max:1000'],
        ]);

        try {
            $this->service->reject($transfer, (int) $request->user()->id, $request->input('rejection_reason'));
        } catch (ValidationException $e) {
            throw $e;
        }

        return redirect()->route('operations.stock-transfers.show', $transfer)
            ->with('success', 'Transfer stok ditolak.');
    }

    public function void(Request $request, StockTransfer $transfer): RedirectResponse
    {
        $request->validate([
            'void_reason' => ['required', 'string', 'max:1000'],
        ]);

        try {
            $this->service->void($transfer, (int) $request->user()->id, $request->input('void_reason'));
        } catch (ValidationException $e) {
            throw $e;
        }

        return redirect()->route('operations.stock-transfers.show', $transfer)
            ->with('success', 'Transfer stok berhasil dibatalkan dan stok sudah dikembalikan.');
    }

    private function tenantId(Request $request): int
    {
        $tenantId = $request->user()?->tenant_id;
        abort_unless($tenantId, 403, 'Tenant belum terpasang pada user.');
        return (int) $tenantId;
    }
}
