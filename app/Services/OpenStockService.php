<?php

namespace App\Services;

use App\Models\User;
use App\Modules\Inventory\Models\Item;
use App\Modules\Operations\Models\OpenStock;
use App\Support\Decimal;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OpenStockService
{
    public function __construct(private readonly StockLedgerService $stockLedgerService)
    {
    }

    /**
     * Create multiple draft open stock records in a single transaction.
     * Returns the collection of created OpenStock models.
     *
     * @param  array<string, mixed>  $data  Must contain outer keys (tenant_id, outlet_id, etc.)
     *                                      and 'items' => array of per-item payloads.
     * @return Collection<int, OpenStock>
     */
    public function createBulkDraft(array $data): Collection
    {
        return $this->createBatchDraft($data, (int) ($data['created_by'] ?? 0));
    }

    /**
     * Create or update draft open stock rows for a batch.
     *
     * @param  array<string, mixed>  $data
     * @return Collection<int, OpenStock>
     */
    public function createBatchDraft(array $data, int $userId): Collection
    {
        return DB::transaction(function () use ($data, $userId): Collection {
            $created = new Collection();

            foreach ($data['items'] as $itemRow) {
                $draftData = array_merge(
                    [
                        'tenant_id'    => $data['tenant_id'],
                        'outlet_id'    => $data['outlet_id'],
                        'stock_target' => $itemRow['stock_target'] ?? $data['stock_target'],
                        'business_date'=> $data['business_date'],
                        'created_by'   => $data['created_by'] ?? $userId ?: null,
                        'notes'        => $itemRow['notes'] ?? $data['batch_notes'] ?? null,
                    ],
                    $itemRow
                );

                $prepared = $this->prepareDraftData($draftData);
                $existing = OpenStock::query()
                    ->where('tenant_id', $prepared['tenant_id'])
                    ->where('outlet_id', $prepared['outlet_id'])
                    ->where('item_id', $prepared['item_id'])
                    ->where('stock_target', $prepared['stock_target'])
                    ->whereDate('business_date', $prepared['business_date'])
                    ->where('status', OpenStock::STATUS_DRAFT)
                    ->lockForUpdate()
                    ->first();

                if ($existing) {
                    $existing->update($prepared);
                    $created->push($existing->refresh());

                    continue;
                }

                $created->push(OpenStock::query()->create(array_merge($prepared, [
                    'status' => OpenStock::STATUS_DRAFT,
                ])));
            }

            return $created;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createDraft(array $data): OpenStock
    {
        $data = $this->prepareDraftData($data);

        return OpenStock::query()->create(array_merge($data, [
            'status' => OpenStock::STATUS_DRAFT,
        ]));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateDraft(OpenStock $openStock, array $data): OpenStock
    {
        $this->assertDraft($openStock);

        $openStock->update($this->prepareDraftData($data));

        return $openStock->refresh();
    }

    public function deleteDraft(OpenStock $openStock): void
    {
        $this->assertDraft($openStock);

        $openStock->delete();
    }

    public function post(OpenStock $openStock, User $user): OpenStock
    {
        return DB::transaction(function () use ($openStock, $user): OpenStock {
            $openStock = OpenStock::query()
                ->with('item')
                ->lockForUpdate()
                ->findOrFail($openStock->id);

            $this->assertDraft($openStock);
            $this->assertNoPostedDuplicate($openStock);

            $qtyInBaseUnit = $this->calculateQtyInBaseUnit(
                $openStock->item,
                $openStock->stock_target,
                $openStock->qty_whole,
                $openStock->qty_loose
            );

            if (bccomp($qtyInBaseUnit, '0.000000', 6) <= 0) {
                throw ValidationException::withMessages([
                    'qty' => 'Minimal salah satu dari qty utuh atau qty ecer harus lebih dari 0.',
                ]);
            }

            $postedAt = Carbon::now();
            $unitId   = $this->baseUnitId($openStock->item);

            $openStock->forceFill([
                'unit_id'          => $unitId,
                'qty_in_base_unit' => $qtyInBaseUnit,
                'status'           => OpenStock::STATUS_POSTED,
                'posted_by'        => $user->id,
                'posted_at'        => $postedAt,
            ])->save();

            $mutation = $this->stockLedgerService->openStock([
                'tenant_id'      => $openStock->tenant_id,
                'outlet_id'      => $openStock->outlet_id,
                'item_id'        => $openStock->item_id,
                'unit_id'        => $unitId,
                'stock_target'   => $openStock->stock_target,
                'qty'            => $qtyInBaseUnit,
                'reference_type' => OpenStock::class,
                'reference_id'   => $openStock->id,
                'performed_by'   => $user->id,
                'performed_at'   => $postedAt,
                'metadata'       => [
                    'qty_whole'    => (string) $openStock->qty_whole,
                    'qty_loose'    => (string) $openStock->qty_loose,
                    'cost_per_unit'=> $openStock->cost_per_unit === null ? null : (string) $openStock->cost_per_unit,
                ],
            ]);

            $openStock->forceFill(['mutation_id' => $mutation->id])->save();

            return $openStock->refresh();
        });
    }

    /**
     * Void a POSTED open stock — creates a VOID_REVERSAL in the stock ledger.
     * The open_stock status becomes VOID. The original mutation is never deleted.
     */
    public function void(OpenStock $openStock, User $user, string $reason): OpenStock
    {
        return DB::transaction(function () use ($openStock, $user, $reason): OpenStock {
            $openStock = OpenStock::query()
                ->lockForUpdate()
                ->findOrFail($openStock->id);

            if ($openStock->status !== OpenStock::STATUS_POSTED) {
                throw ValidationException::withMessages([
                    'status' => 'Hanya Open Stock dengan status POSTED yang dapat di-void.',
                ]);
            }

            $this->stockLedgerService->voidMutation($openStock->mutation_id, [
                'performed_by' => $user->id,
                'void_reason'  => $reason,
                'notes'        => "VOID Open Stock #{$openStock->id}: {$reason}",
                'performed_at' => Carbon::now(),
            ]);

            $openStock->forceFill([
                'status'      => OpenStock::STATUS_VOID,
                'voided_by'   => $user->id,
                'voided_at'   => Carbon::now(),
                'void_reason' => $reason,
            ])->save();

            return $openStock->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function prepareDraftData(array $data): array
    {
        $item     = Item::query()
            ->where('tenant_id', $data['tenant_id'])
            ->findOrFail($data['item_id']);
        $qtyWhole = Decimal::toFixed($data['qty_whole'] ?? '0');
        $qtyLoose = Decimal::toFixed($data['qty_loose'] ?? '0');

        return [
            'tenant_id'       => $data['tenant_id'],
            'outlet_id'       => $data['outlet_id'],
            'department_id'   => $data['department_id'] ?? null,
            'item_id'         => $item->id,
            'unit_id'         => $this->baseUnitId($item),
            'stock_target'    => $data['stock_target'],
            'business_date'   => $data['business_date'],
            'qty_whole'       => $qtyWhole,
            'qty_loose'       => $qtyLoose,
            'qty_in_base_unit'=> $this->calculateQtyInBaseUnit($item, (string) $data['stock_target'], $qtyWhole, $qtyLoose),
            'cost_per_unit'   => isset($data['cost_per_unit']) && $data['cost_per_unit'] !== ''
                ? Decimal::toFixed($data['cost_per_unit'], 4)
                : null,
            'created_by'      => $data['created_by'] ?? null,
            'notes'           => $data['notes'] ?? null,
        ];
    }

    private function assertDraft(OpenStock $openStock): void
    {
        if ($openStock->status !== OpenStock::STATUS_DRAFT) {
            throw ValidationException::withMessages([
                'status' => 'Open Stock yang sudah POSTED tidak boleh diubah atau dihapus.',
            ]);
        }
    }

    private function assertNoPostedDuplicate(OpenStock $openStock): void
    {
        $exists = OpenStock::query()
            ->where('tenant_id', $openStock->tenant_id)
            ->where('outlet_id', $openStock->outlet_id)
            ->where('item_id', $openStock->item_id)
            ->where('stock_target', $openStock->stock_target)
            ->whereDate('business_date', $openStock->business_date)
            ->where('status', OpenStock::STATUS_POSTED)
            ->whereKeyNot($openStock->id)
            ->lockForUpdate()
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'item_id' => 'Item ini sudah pernah diposting untuk outlet, target, dan tanggal yang sama.',
            ]);
        }
    }

    private function calculateQtyInBaseUnit(Item $item, string $stockTarget, mixed $qtyWhole, mixed $qtyLoose): string
    {
        $whole = Decimal::toFixed($qtyWhole);
        $loose = Decimal::toFixed($qtyLoose);

        $ratioSource = $stockTarget === OpenStock::TARGET_OUTLET_WAREHOUSE
            ? $item->purchase_ratio
            : $item->inventory_ratio;

        $ratio = $ratioSource !== null && bccomp((string) $ratioSource, '0.000000', 6) > 0
            ? Decimal::toFixed($ratioSource)
            : '1.000000';

        return bcadd(bcmul($whole, $ratio, 6), $loose, 6);
    }

    private function baseUnitId(Item $item): int
    {
        return (int) ($item->base_unit_id ?: $item->inventory_unit_id);
    }
}
