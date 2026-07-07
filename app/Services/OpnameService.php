<?php

namespace App\Services;

use App\Modules\Core\Models\Outlet;
use App\Modules\Inventory\Models\Item;
use App\Modules\Operations\Models\OpnameItem;
use App\Modules\Operations\Models\OpnameSession;
use App\Modules\Stock\Models\StockBalance;
use App\Modules\Stock\Models\StockMutation;
use App\Support\Decimal;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OpnameService
{
    public function __construct(private readonly StockLedgerService $stockLedgerService)
    {
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function startSession(array $data, int $userId): OpnameSession
    {
        return DB::transaction(function () use ($data, $userId): OpnameSession {
            $tenantId = (int) $data['tenant_id'];
            $outletId = (int) $data['outlet_id'];
            $opnameDate = Carbon::parse($data['opname_date'])->toDateString();
            $type = $data['type'] ?? OpnameSession::TYPE_DAILY;

            $this->assertOutletBelongsToTenant($outletId, $tenantId);

            $openSessionExists = OpnameSession::query()
                ->where('tenant_id', $tenantId)
                ->where('outlet_id', $outletId)
                ->whereDate('opname_date', $opnameDate)
                ->where('type', $type)
                ->whereIn('status', [OpnameSession::STATUS_DRAFT, OpnameSession::STATUS_SUBMITTED])
                ->exists();

            if ($openSessionExists) {
                throw ValidationException::withMessages([
                    'opname_date' => 'Masih ada sesi opname draft/submitted untuk outlet dan tanggal ini.',
                ]);
            }

            $session = OpnameSession::query()->create([
                'tenant_id' => $tenantId,
                'outlet_id' => $outletId,
                'department_id' => $data['department_id'] ?? null,
                'type' => $type,
                'opname_type' => $type,
                'opname_date' => $opnameDate,
                'business_date' => $opnameDate,
                'shift' => $data['shift'] ?? null,
                'status' => OpnameSession::STATUS_DRAFT,
                'notes' => $data['notes'] ?? null,
                'created_by' => $userId,
                'started_by' => $userId,
            ]);

            foreach ($this->itemsForOpname($tenantId, $outletId, $type) as $item) {
                $systemQty = $this->systemQty($tenantId, $outletId, (int) $item->id);

                $session->items()->create([
                    'tenant_id' => $tenantId,
                    'item_id' => $item->id,
                    'unit_id' => $item->inventory_unit_id ?: $item->base_unit_id,
                    'department_id' => $item->primary_department_id,
                    'system_qty' => $systemQty,
                    'system_qty_base' => $systemQty,
                    'counted_qty' => '0.000000',
                    'physical_qty_whole' => '0.000000',
                    'physical_qty_loose' => '0.000000',
                    'physical_qty_base' => '0.000000',
                    'variance_qty' => bcmul($systemQty, '-1', 6),
                    'variance' => bcmul($systemQty, '-1', 6),
                    'variance_value' => '0.0000',
                    'is_counted' => false,
                ]);
            }

            return $session->load(['outlet', 'items.item.inventoryUnit', 'items.item.baseUnit']);
        });
    }

    public function updateItem(OpnameItem $opnameItem, mixed $wholeQty, mixed $looseQty): OpnameItem
    {
        return DB::transaction(function () use ($opnameItem, $wholeQty, $looseQty): OpnameItem {
            $opnameItem = OpnameItem::query()
                ->with(['session', 'item'])
                ->lockForUpdate()
                ->findOrFail($opnameItem->id);

            if ($opnameItem->session->status !== OpnameSession::STATUS_DRAFT) {
                throw ValidationException::withMessages([
                    'status' => 'Item hanya bisa diubah saat sesi masih draft.',
                ]);
            }

            $whole = Decimal::toFixed($wholeQty ?? 0, 6);
            $loose = Decimal::toFixed($looseQty ?? 0, 6);
            $physicalBase = $this->physicalBaseQty($opnameItem->item, $whole, $loose);
            $variance = bcsub($physicalBase, (string) $opnameItem->system_qty_base, 6);
            $cost = Decimal::toFixed($opnameItem->item?->standard_cost ?: $opnameItem->item?->last_purchase_price ?: 0, 4);
            $varianceValue = bcmul(ltrim($variance, '-'), $cost, 4);

            $opnameItem->update([
                'physical_qty_whole' => $whole,
                'physical_qty_loose' => $loose,
                'physical_qty_base' => $physicalBase,
                'counted_qty' => $physicalBase,
                'variance_qty' => $variance,
                'variance' => $variance,
                'variance_value' => $varianceValue,
                'is_counted' => true,
            ]);

            return $opnameItem->refresh()->load(['item.inventoryUnit', 'item.baseUnit', 'department']);
        });
    }

    public function submit(OpnameSession $session, int $userId): OpnameSession
    {
        return DB::transaction(function () use ($session, $userId): OpnameSession {
            $session = OpnameSession::query()
                ->with('items')
                ->lockForUpdate()
                ->findOrFail($session->id);

            if ($session->status !== OpnameSession::STATUS_DRAFT) {
                throw ValidationException::withMessages([
                    'status' => 'Hanya sesi draft yang bisa disubmit.',
                ]);
            }

            if ($session->items->isEmpty()) {
                throw ValidationException::withMessages([
                    'items' => 'Tidak ada item untuk opname.',
                ]);
            }

            if ($session->items->contains(fn (OpnameItem $item): bool => ! $item->is_counted)) {
                throw ValidationException::withMessages([
                    'items' => 'Semua item wajib diisi sebelum submit.',
                ]);
            }

            $session->update([
                'status' => OpnameSession::STATUS_SUBMITTED,
                'submitted_by' => $userId,
                'submitted_at' => now(),
            ]);

            return $session->refresh();
        });
    }

    public function approve(OpnameSession $session, int $userId): OpnameSession
    {
        return DB::transaction(function () use ($session, $userId): OpnameSession {
            $session = OpnameSession::query()
                ->with(['items.item'])
                ->lockForUpdate()
                ->findOrFail($session->id);

            if ($session->status !== OpnameSession::STATUS_SUBMITTED) {
                throw ValidationException::withMessages([
                    'status' => 'Hanya sesi submitted yang bisa di-approve.',
                ]);
            }

            foreach ($session->items as $opnameItem) {
                if (bccomp((string) $opnameItem->variance, '0.000000', 6) === 0 || $opnameItem->mutation_id) {
                    continue;
                }

                $mutation = $this->stockLedgerService->opnameAdjustment([
                    'tenant_id' => $session->tenant_id,
                    'outlet_id' => $session->outlet_id,
                    'item_id' => $opnameItem->item_id,
                    'unit_id' => $opnameItem->item?->base_unit_id ?: $opnameItem->unit_id,
                    'stock_target' => StockMutation::TARGET_OUTLET_DAILY,
                    'opname_type' => $session->type,
                    'qty_change' => (string) $opnameItem->variance,
                    'reference_type' => OpnameItem::class,
                    'reference_id' => $opnameItem->id,
                    'performed_by' => $userId,
                    'performed_at' => now(),
                    'notes' => "Opname {$session->type} {$session->opname_date?->format('Y-m-d')}",
                    'metadata' => [
                        'opname_session_id' => $session->id,
                        'opname_item_id' => $opnameItem->id,
                    ],
                ]);

                $opnameItem->update(['mutation_id' => $mutation->id]);
            }

            $session->update([
                'status' => OpnameSession::STATUS_PROCESSED,
                'approved_by' => $userId,
                'approved_at' => now(),
                'posted_by' => $userId,
                'posted_at' => now(),
            ]);

            return $session->refresh()->load(['items.item', 'items.mutation']);
        });
    }

    public function countDailyItems(int $tenantId, int $outletId): int
    {
        return $this->itemsForOpname($tenantId, $outletId, OpnameSession::TYPE_DAILY)->count();
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
     * @return Collection<int, Item>
     */
    private function itemsForOpname(int $tenantId, int $outletId, string $type): Collection
    {
        $frequency = $type === OpnameSession::TYPE_MONTHLY ? 'MONTHLY' : 'DAILY';

        $itemIds = DB::table('item_outlets')
            ->where('tenant_id', $tenantId)
            ->where('outlet_id', $outletId)
            ->where(function ($query): void {
                $query->where('status', 'ACTIVE')->orWhere('is_active', true);
            })
            ->where(function ($query) use ($frequency): void {
                $query->where('opname_frequency', $frequency)->orWhereNull('opname_frequency');
            })
            ->pluck('item_id');

        $query = Item::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->where('track_stock', true)
            ->with(['baseUnit', 'inventoryUnit', 'primaryDepartment'])
            ->orderBy('name');

        if ($itemIds->isNotEmpty()) {
            $query->whereIn('id', $itemIds->all());
        }

        return $query->get();
    }

    private function systemQty(int $tenantId, int $outletId, int $itemId): string
    {
        $balance = StockBalance::query()
            ->where('tenant_id', $tenantId)
            ->where('outlet_id', $outletId)
            ->where('item_id', $itemId)
            ->where('stock_target', StockMutation::TARGET_OUTLET_DAILY)
            ->first();

        return Decimal::toFixed($balance?->qty_on_hand ?? 0, 6);
    }

    private function physicalBaseQty(?Item $item, string $whole, string $loose): string
    {
        if (! $item) {
            return bcadd($whole, $loose, 6);
        }

        $ratio = $item->inventory_ratio ? Decimal::toFixed($item->inventory_ratio, 6) : '1.000000';

        return bcadd(bcmul($whole, $ratio, 6), $loose, 6);
    }
}
