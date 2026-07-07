<?php

namespace App\Services;

use App\Exceptions\InsufficientStockException;
use App\Modules\Core\Models\Outlet;
use App\Modules\Inventory\Models\Item;
use App\Modules\Inventory\Models\UnitConversion;
use App\Modules\Operations\Models\SpoilWaste;
use App\Modules\Stock\Models\StockBalance;
use App\Modules\Stock\Models\StockMutation;
use App\Support\Decimal;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SpoilWasteService
{
    public function __construct(private readonly StockLedgerService $stockLedgerService)
    {
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function record(array $data, int $userId): SpoilWaste
    {
        return DB::transaction(function () use ($data, $userId): SpoilWaste {
            $tenantId = (int) $data['tenant_id'];
            $outletId = (int) $data['outlet_id'];
            $item = $this->itemForTenant((int) $data['item_id'], $tenantId);
            $unitId = (int) ($data['unit_id'] ?? $item->inventory_unit_id ?? $item->base_unit_id);
            $this->assertUnitBelongsToTenant($unitId, $tenantId);
            $this->assertOutletBelongsToTenant($outletId, $tenantId);

            $qty = Decimal::toFixed($data['qty'], 6);
            $qtyInBase = $this->calculateBaseQty($item, $unitId, $qty);
            $stockTarget = $data['stock_target'] ?? StockMutation::TARGET_OUTLET_DAILY;

            $balance = StockBalance::query()
                ->where('tenant_id', $tenantId)
                ->where('outlet_id', $outletId)
                ->where('item_id', $item->id)
                ->where('stock_target', $stockTarget)
                ->lockForUpdate()
                ->first();

            if (! $balance || bccomp((string) $balance->qty_on_hand, $qtyInBase, 6) < 0) {
                throw new InsufficientStockException(
                    'Stok tidak cukup untuk spoil. Tersedia: '.($balance?->qty_on_hand ?? '0').' (base unit)'
                );
            }

            [$photoPath, $photoHash, $photoMeta, $duplicate] = $this->handlePhoto($data['photo_file'] ?? null, $tenantId);

            $recordedAt = Carbon::parse($data['recorded_at'] ?? now());

            $spoil = SpoilWaste::query()->create([
                'tenant_id' => $tenantId,
                'outlet_id' => $outletId,
                'department_id' => $data['department_id'] ?? null,
                'item_id' => $item->id,
                'unit_id' => $unitId,
                'qty' => $qty,
                'qty_in_base_unit' => $qtyInBase,
                'reason_category' => $data['reason_category'],
                'reason_detail' => $data['reason_detail'] ?? null,
                'photo' => $photoPath,
                'photo_path' => $photoPath,
                'photo_hash' => $photoHash,
                'photo_meta' => $photoMeta,
                'is_duplicate_photo' => (bool) $duplicate,
                'duplicate_ref_id' => $duplicate?->id,
                'recorded_date' => Carbon::parse($data['recorded_date'] ?? $recordedAt)->toDateString(),
                'recorded_at' => $recordedAt,
                'status' => SpoilWaste::STATUS_PENDING,
                'approval_status' => SpoilWaste::STATUS_PENDING,
                'created_by' => $userId,
                'device_info' => $data['device_info'] ?? null,
                'ip_address' => $data['ip_address'] ?? null,
                'notes' => $data['reason_detail'] ?? null,
            ]);

            $mutation = $this->stockLedgerService->spoilWaste([
                'tenant_id' => $tenantId,
                'outlet_id' => $outletId,
                'item_id' => $item->id,
                'unit_id' => $item->base_unit_id ?: $unitId,
                'stock_target' => $stockTarget,
                'qty_change' => $qtyInBase,
                'reference_type' => SpoilWaste::class,
                'reference_id' => $spoil->id,
                'performed_by' => $userId,
                'performed_at' => $recordedAt,
                'notes' => "Spoil: {$spoil->reason_category}",
                'metadata' => [
                    'spoil_waste_id' => $spoil->id,
                    'stock_target' => $stockTarget,
                    'duplicate_ref_id' => $duplicate?->id,
                ],
            ]);

            $spoil->update(['mutation_id' => $mutation->id]);

            return $spoil->refresh()->load(['outlet', 'department', 'item', 'unit', 'mutation', 'duplicateReference']);
        });
    }

    public function approve(SpoilWaste $spoil, int $userId, string $notes = ''): SpoilWaste
    {
        return DB::transaction(function () use ($spoil, $userId, $notes): SpoilWaste {
            $spoil = SpoilWaste::query()->lockForUpdate()->findOrFail($spoil->id);

            if ($spoil->status !== SpoilWaste::STATUS_PENDING) {
                throw ValidationException::withMessages([
                    'status' => 'Hanya spoil berstatus pending yang bisa di-approve.',
                ]);
            }

            $spoil->update([
                'status' => SpoilWaste::STATUS_APPROVED,
                'approval_status' => SpoilWaste::STATUS_APPROVED,
                'approved_by' => $userId,
                'approved_at' => now(),
                'approval_notes' => $notes,
            ]);

            return $spoil->refresh();
        });
    }

    public function reject(SpoilWaste $spoil, int $userId, string $reason): SpoilWaste
    {
        return DB::transaction(function () use ($spoil, $userId, $reason): SpoilWaste {
            $spoil = SpoilWaste::query()->with('mutation')->lockForUpdate()->findOrFail($spoil->id);

            if ($spoil->status !== SpoilWaste::STATUS_PENDING) {
                throw ValidationException::withMessages([
                    'status' => 'Hanya spoil berstatus pending yang bisa ditolak.',
                ]);
            }

            if ($spoil->mutation) {
                $this->stockLedgerService->voidMutation($spoil->mutation, [
                    'reference_type' => SpoilWaste::class,
                    'reference_id' => $spoil->id,
                    'performed_by' => $userId,
                    'void_reason' => "Spoil ditolak: {$reason}",
                    'notes' => "Spoil ditolak: {$reason}",
                ]);
            }

            $spoil->update([
                'status' => SpoilWaste::STATUS_REJECTED,
                'approval_status' => SpoilWaste::STATUS_REJECTED,
                'approved_by' => $userId,
                'approved_at' => now(),
                'approval_notes' => $reason,
            ]);

            return $spoil->refresh();
        });
    }

    /**
     * @return array{0: string|null, 1: string|null, 2: array<string, mixed>|null, 3: SpoilWaste|null}
     */
    private function handlePhoto(mixed $file, int $tenantId): array
    {
        if (! $file instanceof UploadedFile) {
            return [null, null, null, null];
        }

        $photoHash = hash_file('sha256', $file->path());

        $duplicate = SpoilWaste::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('photo_hash', $photoHash)
            ->latest()
            ->first();

        $photoPath = $file->store("tenants/{$tenantId}/spoil", 'public');

        return [
            $photoPath,
            $photoHash,
            [
                'size' => $file->getSize(),
                'mime' => $file->getMimeType(),
                'original_name' => $file->getClientOriginalName(),
            ],
            $duplicate,
        ];
    }

    private function itemForTenant(int $itemId, int $tenantId): Item
    {
        $item = Item::query()
            ->where('tenant_id', $tenantId)
            ->with(['baseUnit', 'inventoryUnit', 'purchaseUnit'])
            ->find($itemId);

        if (! $item) {
            throw ValidationException::withMessages([
                'item_id' => 'Item tidak valid untuk tenant ini.',
            ]);
        }

        return $item;
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

    private function calculateBaseQty(Item $item, int $unitId, string $qty): string
    {
        $factor = '1.000000';

        if ((int) $item->inventory_unit_id === $unitId && $item->inventory_ratio) {
            $factor = Decimal::toFixed($item->inventory_ratio, 6);
        } elseif ((int) $item->base_unit_id === $unitId) {
            $factor = '1.000000';
        } elseif ((int) $item->purchase_unit_id === $unitId && $item->purchase_ratio) {
            $factor = Decimal::toFixed($item->purchase_ratio, 6);
        } else {
            $conversion = UnitConversion::query()
                ->where('tenant_id', $item->tenant_id)
                ->where('item_id', $item->id)
                ->where('from_unit_id', $unitId)
                ->where('to_unit_id', $item->base_unit_id)
                ->first();

            if ($conversion) {
                $factor = Decimal::toFixed($conversion->factor, 6);
            }
        }

        return bcmul($qty, $factor, 6);
    }
}
