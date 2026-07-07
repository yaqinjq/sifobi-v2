<?php

namespace App\Services;

use App\Modules\Core\Models\Outlet;
use App\Modules\Inventory\Models\Item;
use App\Modules\Inventory\Models\UnitConversion;
use App\Modules\Receiving\Models\GoodsReceipt;
use App\Modules\Receiving\Models\Supplier;
use App\Modules\Stock\Models\StockMutation;
use App\Support\Decimal;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GoodsReceiptService
{
    public function __construct(private readonly StockLedgerService $stockLedgerService)
    {
    }

    public function generateCode(int $tenantId): string
    {
        $prefix = 'GR-'.now()->format('ym').'-';

        $latestCode = GoodsReceipt::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('code', 'like', $prefix.'%')
            ->lockForUpdate()
            ->orderByDesc('code')
            ->value('code');

        $nextNumber = $latestCode ? ((int) substr((string) $latestCode, -4)) + 1 : 1;

        return $prefix.str_pad((string) $nextNumber, 4, '0', STR_PAD_LEFT);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createDraft(array $data, int $userId): GoodsReceipt
    {
        return DB::transaction(function () use ($data, $userId): GoodsReceipt {
            $tenantId = (int) $data['tenant_id'];
            $this->assertOutletBelongsToTenant((int) $data['outlet_id'], $tenantId);
            $supplierName = $this->resolveSupplierName($data, $tenantId);
            $code = $data['code'] ?? $this->generateCode($tenantId);
            $receiptDate = Carbon::parse($data['receipt_date'] ?? now())->toDateString();

            $receipt = GoodsReceipt::query()->create([
                'tenant_id' => $tenantId,
                'code' => $code,
                'receipt_number' => $code,
                'outlet_id' => (int) $data['outlet_id'],
                'source' => $data['source'],
                'source_type' => $data['source'],
                'source_reference' => $data['external_po_number'] ?? $data['doc_number'] ?? null,
                'external_po_number' => $data['external_po_number'] ?? null,
                'supplier_id' => $data['supplier_id'] ?? null,
                'supplier_name' => $supplierName,
                'vendor_name' => $supplierName,
                'doc_number' => $data['doc_number'] ?? null,
                'invoice_number' => $data['invoice_number'] ?? null,
                'photo_document' => $data['photo_document'] ?? null,
                'receipt_date' => $receiptDate,
                'received_at' => $data['received_at'] ?? now(),
                'status' => GoodsReceipt::STATUS_DRAFT,
                'review_status' => GoodsReceipt::REVIEW_NONE,
                'created_by' => $userId,
                'received_by' => $userId,
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($this->prepareItems($data['items'] ?? [], $tenantId) as $itemData) {
                $receipt->items()->create($itemData);
            }

            return $receipt->load(['outlet', 'supplier', 'items.item', 'items.unit']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateDraft(GoodsReceipt $receipt, array $data, int $userId): GoodsReceipt
    {
        return DB::transaction(function () use ($receipt, $data, $userId): GoodsReceipt {
            $receipt = GoodsReceipt::query()->lockForUpdate()->findOrFail($receipt->id);
            $this->assertEditable($receipt);

            $tenantId = (int) $receipt->tenant_id;
            $this->assertOutletBelongsToTenant((int) $data['outlet_id'], $tenantId);
            $supplierName = $this->resolveSupplierName($data, $tenantId);

            $receipt->update([
                'outlet_id' => (int) $data['outlet_id'],
                'source' => $data['source'],
                'source_type' => $data['source'],
                'source_reference' => $data['external_po_number'] ?? $data['doc_number'] ?? null,
                'external_po_number' => $data['external_po_number'] ?? null,
                'supplier_id' => $data['supplier_id'] ?? null,
                'supplier_name' => $supplierName,
                'vendor_name' => $supplierName,
                'doc_number' => $data['doc_number'] ?? null,
                'invoice_number' => $data['invoice_number'] ?? null,
                'photo_document' => $data['photo_document'] ?? $receipt->photo_document,
                'receipt_date' => Carbon::parse($data['receipt_date'] ?? $receipt->receipt_date)->toDateString(),
                'received_at' => $data['received_at'] ?? $receipt->received_at ?? now(),
                'status' => GoodsReceipt::STATUS_DRAFT,
                'review_status' => GoodsReceipt::REVIEW_NONE,
                'review_notes' => null,
                'notes' => $data['notes'] ?? null,
                'received_by' => $userId,
            ]);

            $receipt->items()->delete();

            foreach ($this->prepareItems($data['items'] ?? [], $tenantId) as $itemData) {
                $receipt->items()->create($itemData);
            }

            return $receipt->refresh()->load(['outlet', 'supplier', 'items.item', 'items.unit']);
        });
    }

    public function submit(GoodsReceipt $receipt, int $userId): GoodsReceipt
    {
        return DB::transaction(function () use ($receipt, $userId): GoodsReceipt {
            $receipt = GoodsReceipt::query()
                ->with(['items.item'])
                ->lockForUpdate()
                ->findOrFail($receipt->id);

            if (! in_array($receipt->status, [GoodsReceipt::STATUS_DRAFT, GoodsReceipt::STATUS_REJECTED], true)) {
                throw ValidationException::withMessages([
                    'status' => 'Hanya draft atau dokumen yang ditolak yang bisa disubmit.',
                ]);
            }

            $this->assertReadyToSubmit($receipt);

            $receipt->update([
                'status' => GoodsReceipt::STATUS_SUBMITTED,
                'submitted_by' => $userId,
                'review_status' => GoodsReceipt::REVIEW_NEED_REVIEW,
            ]);

            return $receipt->refresh()->load(['outlet', 'supplier', 'items.item', 'items.unit']);
        });
    }

    public function approve(GoodsReceipt $receipt, int $userId, string $notes = ''): GoodsReceipt
    {
        return DB::transaction(function () use ($receipt, $userId, $notes): GoodsReceipt {
            $receipt = GoodsReceipt::query()
                ->with(['items.item'])
                ->lockForUpdate()
                ->findOrFail($receipt->id);

            if ($receipt->status !== GoodsReceipt::STATUS_SUBMITTED) {
                throw ValidationException::withMessages([
                    'status' => 'Hanya dokumen submitted yang bisa di-approve.',
                ]);
            }

            $receipt->update([
                'status' => GoodsReceipt::STATUS_APPROVED,
                'review_status' => GoodsReceipt::REVIEW_APPROVED,
                'reviewed_by' => $userId,
                'reviewed_at' => now(),
                'approved_by' => $userId,
                'approved_at' => now(),
                'review_notes' => $notes,
            ]);

            $this->postToLedger($receipt->refresh(), $userId);

            return $receipt->refresh()->load(['outlet', 'supplier', 'items.item', 'items.unit', 'items.mutation']);
        });
    }

    public function reject(GoodsReceipt $receipt, int $userId, string $reason): GoodsReceipt
    {
        return DB::transaction(function () use ($receipt, $userId, $reason): GoodsReceipt {
            $receipt = GoodsReceipt::query()->lockForUpdate()->findOrFail($receipt->id);

            if ($receipt->status !== GoodsReceipt::STATUS_SUBMITTED) {
                throw ValidationException::withMessages([
                    'status' => 'Hanya dokumen submitted yang bisa ditolak.',
                ]);
            }

            $receipt->update([
                'status' => GoodsReceipt::STATUS_REJECTED,
                'review_status' => GoodsReceipt::REVIEW_REJECTED,
                'reviewed_by' => $userId,
                'reviewed_at' => now(),
                'review_notes' => $reason,
            ]);

            return $receipt->refresh()->load(['outlet', 'supplier', 'items.item', 'items.unit']);
        });
    }

    public function postToLedger(GoodsReceipt $receipt, int $userId): void
    {
        DB::transaction(function () use ($receipt, $userId): void {
            $receipt = GoodsReceipt::query()
                ->with(['items.item'])
                ->lockForUpdate()
                ->findOrFail($receipt->id);

            if (! in_array($receipt->status, [GoodsReceipt::STATUS_APPROVED, GoodsReceipt::STATUS_POSTED], true)) {
                throw ValidationException::withMessages([
                    'status' => 'Dokumen harus approved sebelum masuk stock ledger.',
                ]);
            }

            if ($receipt->status === GoodsReceipt::STATUS_POSTED) {
                return;
            }

            foreach ($receipt->items as $receiptItem) {
                if ($receiptItem->mutation_id) {
                    continue;
                }

                $item = $receiptItem->item;
                if (! $item) {
                    throw ValidationException::withMessages([
                        'item_id' => 'Item penerimaan tidak ditemukan.',
                    ]);
                }

                $mutation = $this->stockLedgerService->receivePurchaseOrder([
                    'tenant_id' => $receipt->tenant_id,
                    'outlet_id' => $receipt->outlet_id,
                    'item_id' => $receiptItem->item_id,
                    'unit_id' => $item->base_unit_id ?: $receiptItem->unit_id,
                    'stock_target' => StockMutation::TARGET_OUTLET_WAREHOUSE,
                    'qty_change' => Decimal::toFixed($receiptItem->qty_in_base_unit, 6),
                    'reference_type' => GoodsReceipt::class,
                    'reference_id' => $receipt->id,
                    'performed_by' => $userId,
                    'performed_at' => now(),
                    'notes' => "Penerimaan {$receipt->code}",
                    'metadata' => [
                        'goods_receipt_item_id' => $receiptItem->id,
                        'source' => $receipt->source,
                        'stock_target' => StockMutation::TARGET_OUTLET_WAREHOUSE,
                        'qty_received' => (string) $receiptItem->qty_received,
                        'qty_in_base_unit' => (string) $receiptItem->qty_in_base_unit,
                        'unit_price' => (string) $receiptItem->unit_price,
                        'total_value' => (string) $receiptItem->total_value,
                        'unit_cost_base' => bccomp((string) $receiptItem->qty_in_base_unit, '0.000000', 6) > 0
                            ? bcdiv((string) $receiptItem->total_value, (string) $receiptItem->qty_in_base_unit, 4)
                            : '0.0000',
                    ],
                ]);

                $receiptItem->update(['mutation_id' => $mutation->id]);
            }

            $receipt->update(['status' => GoodsReceipt::STATUS_POSTED]);
        });
    }

    private function assertOutletBelongsToTenant(int $outletId, int $tenantId): void
    {
        $exists = Outlet::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('id', $outletId)
            ->exists();

        if (! $exists) {
            throw ValidationException::withMessages([
                'outlet_id' => 'Outlet tidak valid untuk tenant ini.',
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolveSupplierName(array $data, int $tenantId): ?string
    {
        if (! empty($data['supplier_id'])) {
            $supplier = Supplier::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->find((int) $data['supplier_id']);

            if (! $supplier) {
                throw ValidationException::withMessages([
                    'supplier_id' => 'Supplier tidak valid untuk tenant ini.',
                ]);
            }

            return $supplier->name;
        }

        return $data['supplier_name'] ?? null;
    }

    private function assertEditable(GoodsReceipt $receipt): void
    {
        if (! in_array($receipt->status, [GoodsReceipt::STATUS_DRAFT, GoodsReceipt::STATUS_REJECTED], true)) {
            throw ValidationException::withMessages([
                'status' => 'Penerimaan yang sudah submitted atau posted tidak bisa diedit.',
            ]);
        }
    }

    private function assertReadyToSubmit(GoodsReceipt $receipt): void
    {
        if ($receipt->items->isEmpty()) {
            throw ValidationException::withMessages([
                'items' => 'Minimal satu item wajib diisi.',
            ]);
        }

        foreach ($receipt->items as $receiptItem) {
            if (bccomp((string) $receiptItem->qty_in_base_unit, '0.000000', 6) <= 0) {
                throw ValidationException::withMessages([
                    'items' => 'Qty terima harus lebih dari 0.',
                ]);
            }

            if ($receiptItem->item?->track_expiry && ! $receiptItem->expired_date) {
                throw ValidationException::withMessages([
                    'expired_date' => "Item {$receiptItem->item->name} wajib isi tanggal expired.",
                ]);
            }
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    private function prepareItems(array $items, int $tenantId): array
    {
        $prepared = [];

        foreach ($items as $row) {
            if (empty($row['item_id'])) {
                continue;
            }

            $item = Item::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->with(['baseUnit', 'inventoryUnit', 'purchaseUnit'])
                ->find((int) $row['item_id']);

            if (! $item) {
                throw ValidationException::withMessages([
                    'item_id' => 'Item tidak valid untuk tenant ini.',
                ]);
            }

            $unitId = (int) ($row['unit_id'] ?? $item->base_unit_id);
            $this->assertUnitBelongsToTenant($unitId, $tenantId);

            $qtyOrdered = Decimal::toFixed($row['qty_ordered'] ?? 0, 6);
            $qtyReceived = Decimal::toFixed($row['qty_received'] ?? 0, 6);
            $unitPrice = Decimal::toFixed($row['unit_price'] ?? 0, 4);
            $qtyInBase = $this->calculateBaseQty($item, $unitId, $qtyReceived);
            $totalValue = bcmul($qtyReceived, $unitPrice, 4);
            $variance = bcsub($qtyReceived, $qtyOrdered, 6);

            $prepared[] = [
                'tenant_id' => $tenantId,
                'item_id' => $item->id,
                'unit_id' => $unitId,
                'qty_ordered' => $qtyOrdered,
                'qty_received' => $qtyReceived,
                'qty_in_base_unit' => $qtyInBase,
                'qty_short' => bccomp($variance, '0.000000', 6) < 0 ? bcmul($variance, '-1', 6) : '0.000000',
                'qty_over' => bccomp($variance, '0.000000', 6) > 0 ? $variance : '0.000000',
                'unit_price' => $unitPrice,
                'unit_cost' => $unitPrice,
                'total_value' => $totalValue,
                'item_status' => $this->itemStatus($qtyOrdered, $qtyReceived),
                'expired_date' => $row['expired_date'] ?? null,
                'batch_code' => $row['batch_code'] ?? null,
                'notes' => $row['notes'] ?? null,
            ];
        }

        return $prepared;
    }

    private function assertUnitBelongsToTenant(int $unitId, int $tenantId): void
    {
        $exists = DB::table('units')
            ->where('tenant_id', $tenantId)
            ->where('id', $unitId)
            ->exists();

        if (! $exists) {
            throw ValidationException::withMessages([
                'unit_id' => 'Satuan tidak valid untuk tenant ini.',
            ]);
        }
    }

    private function calculateBaseQty(Item $item, int $unitId, string $qtyReceived): string
    {
        $factor = '1.000000';

        if ((int) $item->base_unit_id === $unitId) {
            $factor = '1.000000';
        } elseif ((int) $item->inventory_unit_id === $unitId && $item->inventory_ratio) {
            $factor = Decimal::toFixed($item->inventory_ratio, 6);
        } elseif ((int) $item->purchase_unit_id === $unitId && $item->purchase_ratio) {
            $factor = Decimal::toFixed($item->purchase_ratio, 6);
        } else {
            $conversion = UnitConversion::withoutGlobalScopes()
                ->where('tenant_id', $item->tenant_id)
                ->where('item_id', $item->id)
                ->where('from_unit_id', $unitId)
                ->where('to_unit_id', $item->base_unit_id)
                ->first();

            if ($conversion) {
                $factor = Decimal::toFixed($conversion->factor, 6);
            }
        }

        return bcmul($qtyReceived, $factor, 6);
    }

    private function itemStatus(string $qtyOrdered, string $qtyReceived): string
    {
        if (bccomp($qtyOrdered, '0.000000', 6) === 0) {
            return 'OK';
        }

        $comparison = bccomp($qtyReceived, $qtyOrdered, 6);

        return match (true) {
            $comparison < 0 => 'SHORT',
            $comparison > 0 => 'OVER',
            default => 'OK',
        };
    }
}
