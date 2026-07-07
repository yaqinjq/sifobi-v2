<?php

namespace App\Http\Controllers\Receiving;

use App\Http\Controllers\Controller;
use App\Modules\Core\Models\Outlet;
use App\Modules\Inventory\Models\Item;
use App\Modules\Inventory\Models\Unit;
use App\Modules\Receiving\Models\GoodsReceipt;
use App\Modules\Receiving\Models\Supplier;
use App\Services\GoodsReceiptService;
use App\Support\Decimal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class GoodsReceiptController extends Controller
{
    public function __construct(private readonly GoodsReceiptService $goodsReceiptService)
    {
    }

    public function index(Request $request): View
    {
        $receipts = GoodsReceipt::query()
            ->with(['outlet', 'supplier'])
            ->withCount('items')
            ->withSum('items as total_value_sum', 'total_value')
            ->when($request->filled('source'), fn ($query) => $query->where('source', $request->string('source')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('date_from'), fn ($query) => $query->whereDate('receipt_date', '>=', $request->date('date_from')))
            ->when($request->filled('date_to'), fn ($query) => $query->whereDate('receipt_date', '<=', $request->date('date_to')))
            ->when($request->filled('q'), function ($query) use ($request): void {
                $search = '%'.$request->string('q')->trim().'%';
                $query->where(function ($inner) use ($search): void {
                    $inner->where('code', 'like', $search)
                        ->orWhere('receipt_number', 'like', $search)
                        ->orWhere('supplier_name', 'like', $search)
                        ->orWhere('doc_number', 'like', $search)
                        ->orWhere('invoice_number', 'like', $search);
                });
            })
            ->latest('receipt_date')
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('receiving.goods-receipts.index', [
            'receipts' => $receipts,
            'sources' => $this->sources(),
            'statuses' => $this->statuses(),
        ]);
    }

    public function create(Request $request): View
    {
        $source = $request->string('source')->toString();

        if ($source !== '' && ! array_key_exists($source, $this->sources())) {
            abort(404);
        }

        return view('receiving.goods-receipts.create', array_merge($this->formData($request), [
            'receipt' => new GoodsReceipt([
                'source' => $source ?: null,
                'receipt_date' => now()->toDateString(),
            ]),
            'source' => $source,
            'codePreview' => $this->goodsReceiptService->generateCode($this->tenantId($request)),
        ]));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);
        $tenantId = $this->tenantId($request);

        if ($request->hasFile('photo_document')) {
            $validated['photo_document'] = $request->file('photo_document')
                ->store("tenants/{$tenantId}/receiving", 'public');
        }

        $validated['tenant_id'] = $tenantId;

        $receipt = $this->goodsReceiptService->createDraft($validated, (int) $request->user()->id);

        if ($request->input('action') === 'submit') {
            abort_unless($request->user()->can('submit_goods_receipt'), 403);
            $receipt = $this->goodsReceiptService->submit($receipt, (int) $request->user()->id);
        }

        return redirect()
            ->route('receiving.goods-receipts.show', $receipt)
            ->with('success', $receipt->status === GoodsReceipt::STATUS_SUBMITTED
                ? 'Penerimaan berhasil disubmit untuk review.'
                : 'Draft penerimaan berhasil disimpan.');
    }

    public function show(GoodsReceipt $receipt): View
    {
        $receipt->load([
            'outlet',
            'supplier',
            'createdBy',
            'submittedBy',
            'reviewedBy',
            'items.item.baseUnit',
            'items.unit',
            'items.mutation',
        ]);

        return view('receiving.goods-receipts.show', [
            'receipt' => $receipt,
            'sources' => $this->sources(),
        ]);
    }

    public function edit(Request $request, GoodsReceipt $receipt): View
    {
        abort_unless(in_array($receipt->status, [GoodsReceipt::STATUS_DRAFT, GoodsReceipt::STATUS_REJECTED], true), 403);

        $receipt->load(['items.item', 'items.unit']);

        return view('receiving.goods-receipts.edit', array_merge($this->formData($request), [
            'receipt' => $receipt,
            'source' => (string) $receipt->source,
            'codePreview' => $receipt->code,
        ]));
    }

    public function update(Request $request, GoodsReceipt $receipt): RedirectResponse
    {
        $validated = $this->validated($request);
        $tenantId = $this->tenantId($request);

        if ($request->hasFile('photo_document')) {
            $validated['photo_document'] = $request->file('photo_document')
                ->store("tenants/{$tenantId}/receiving", 'public');
        }

        $updated = $this->goodsReceiptService->updateDraft($receipt, $validated, (int) $request->user()->id);

        if ($request->input('action') === 'submit') {
            abort_unless($request->user()->can('submit_goods_receipt'), 403);
            $updated = $this->goodsReceiptService->submit($updated, (int) $request->user()->id);
        }

        return redirect()
            ->route('receiving.goods-receipts.show', $updated)
            ->with('success', $updated->status === GoodsReceipt::STATUS_SUBMITTED
                ? 'Penerimaan berhasil disubmit ulang.'
                : 'Draft penerimaan berhasil diperbarui.');
    }

    public function submit(Request $request, GoodsReceipt $receipt): RedirectResponse
    {
        $updated = $this->goodsReceiptService->submit($receipt, (int) $request->user()->id);

        return redirect()
            ->route('receiving.goods-receipts.show', $updated)
            ->with('success', 'Penerimaan berhasil disubmit untuk review.');
    }

    public function approve(Request $request, GoodsReceipt $receipt): RedirectResponse
    {
        $validated = $request->validate([
            'review_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $updated = $this->goodsReceiptService->approve(
            $receipt,
            (int) $request->user()->id,
            $validated['review_notes'] ?? ''
        );

        return redirect()
            ->route('receiving.goods-receipts.show', $updated)
            ->with('success', 'Penerimaan di-approve dan sudah masuk stock ledger.');
    }

    public function reject(Request $request, GoodsReceipt $receipt): RedirectResponse
    {
        $validated = $request->validate([
            'review_notes' => ['required', 'string', 'max:1000'],
        ]);

        $updated = $this->goodsReceiptService->reject(
            $receipt,
            (int) $request->user()->id,
            $validated['review_notes']
        );

        return redirect()
            ->route('receiving.goods-receipts.show', $updated)
            ->with('warning', 'Penerimaan ditolak dan bisa direvisi.');
    }

    public function destroy(GoodsReceipt $receipt): RedirectResponse
    {
        abort_unless($receipt->status === GoodsReceipt::STATUS_DRAFT, 403);

        DB::transaction(fn () => $receipt->delete());

        return redirect()
            ->route('receiving.goods-receipts.index')
            ->with('success', 'Draft penerimaan berhasil dihapus.');
    }

    /**
     * @return array<string, string>
     */
    private function sources(): array
    {
        return [
            GoodsReceipt::SOURCE_OCIA_PO => 'Kopi dari OCIA',
            GoodsReceipt::SOURCE_WIP_CENTRAL_KITCHEN => 'WIP Central Kitchen',
            GoodsReceipt::SOURCE_PURCHASING_DRYGOOD => 'Drygood Purchasing',
            GoodsReceipt::SOURCE_SUPPLIER_LUAR => 'Supplier Luar',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function statuses(): array
    {
        return [
            GoodsReceipt::STATUS_DRAFT => 'Draft',
            GoodsReceipt::STATUS_SUBMITTED => 'Submitted',
            GoodsReceipt::STATUS_APPROVED => 'Approved',
            GoodsReceipt::STATUS_REJECTED => 'Rejected',
            GoodsReceipt::STATUS_POSTED => 'Posted',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(Request $request): array
    {
        $tenantId = $this->tenantId($request);

        $items = Item::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->with(['baseUnit', 'inventoryUnit', 'purchaseUnit'])
            ->orderBy('name')
            ->get();

        return [
            'sources' => $this->sources(),
            'outlets' => Outlet::query()
                ->where('tenant_id', $tenantId)
                ->where('status', 'ACTIVE')
                ->orderBy('name')
                ->get(),
            'suppliers' => Supplier::query()
                ->where('tenant_id', $tenantId)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(),
            'items' => $items,
            'units' => Unit::query()
                ->where('tenant_id', $tenantId)
                ->where('status', 'ACTIVE')
                ->orderBy('name')
                ->get(),
            'itemsForAlpine' => $items->map(fn (Item $item): array => [
                'id' => $item->id,
                'name' => $item->name,
                'sku' => $item->canonical_sku,
                'base_unit_id' => $item->base_unit_id,
                'base_unit' => $item->baseUnit?->abbreviation ?? 'base',
                'inventory_unit_id' => $item->inventory_unit_id,
                'inventory_unit' => $item->inventoryUnit?->abbreviation,
                'purchase_unit_id' => $item->purchase_unit_id,
                'purchase_unit' => $item->purchaseUnit?->abbreviation,
                'inventory_ratio' => (float) ($item->inventory_ratio ?? 1),
                'purchase_ratio' => (float) ($item->purchase_ratio ?? 1),
                'track_expiry' => (bool) $item->track_expiry,
            ])->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $tenantId = $this->tenantId($request);

        $validated = $request->validate([
            'source' => ['required', 'string', Rule::in(array_keys($this->sources()))],
            'outlet_id' => [
                'required',
                'integer',
                Rule::exists('outlets', 'id')->where('tenant_id', $tenantId),
            ],
            'external_po_number' => ['nullable', 'string', 'max:120'],
            'supplier_id' => [
                'nullable',
                'integer',
                Rule::exists('suppliers', 'id')->where('tenant_id', $tenantId),
            ],
            'supplier_name' => ['nullable', 'string', 'max:150'],
            'doc_number' => ['nullable', 'string', 'max:120'],
            'invoice_number' => ['nullable', 'string', 'max:120'],
            'photo_document' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'receipt_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_id' => [
                'required',
                'integer',
                Rule::exists('items', 'id')->where('tenant_id', $tenantId),
            ],
            'items.*.unit_id' => [
                'required',
                'integer',
                Rule::exists('units', 'id')->where('tenant_id', $tenantId),
            ],
            'items.*.qty_ordered' => ['nullable', Decimal::validationRule(6)],
            'items.*.qty_received' => ['required', Decimal::validationRule(6)],
            'items.*.unit_price' => ['nullable', Decimal::validationRule(4)],
            'items.*.expired_date' => ['nullable', 'date'],
            'items.*.batch_code' => ['nullable', 'string', 'max:100'],
            'items.*.notes' => ['nullable', 'string', 'max:1000'],
        ]);

        foreach ($validated['items'] as $index => $item) {
            $qtyReceived = Decimal::toFixed($item['qty_received'] ?? 0, 6);

            if (bccomp($qtyReceived, '0.000000', 6) <= 0) {
                throw ValidationException::withMessages([
                    "items.{$index}.qty_received" => 'Qty terima harus lebih dari 0.',
                ]);
            }
        }

        return $validated;
    }

    private function tenantId(Request $request): int
    {
        $tenantId = $request->user()?->tenant_id;

        abort_unless($tenantId, 403, 'Tenant belum terpasang pada user.');

        return (int) $tenantId;
    }
}
