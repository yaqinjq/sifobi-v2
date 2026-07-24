<?php

namespace App\Services;

use App\Modules\Stock\Models\StockBalance;
use App\Modules\Stock\Models\StockMutation;
use App\Modules\Stock\Models\StockTransfer;
use App\Modules\Stock\Models\StockTransferItem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StockTransferService
{
    public function create(array $data, int $userId): StockTransfer
    {
        return StockTransfer::query()->create(array_merge($data, [
            'status'     => StockTransfer::STATUS_DRAFT,
            'created_by' => $userId,
        ]));
    }

    public function submit(StockTransfer $transfer, int $userId): StockTransfer
    {
        if ($transfer->status !== StockTransfer::STATUS_DRAFT) {
            throw ValidationException::withMessages(['status' => 'Transfer hanya bisa disubmit dari status DRAFT.']);
        }

        if ($transfer->items()->count() === 0) {
            throw ValidationException::withMessages(['items' => 'Transfer harus memiliki minimal satu item.']);
        }

        $transfer->forceFill([
            'status'       => StockTransfer::STATUS_SUBMITTED,
            'submitted_by' => $userId,
            'submitted_at' => Carbon::now(),
        ])->save();

        return $transfer->fresh();
    }

    public function approve(StockTransfer $transfer, int $userId): StockTransfer
    {
        if ($transfer->status !== StockTransfer::STATUS_SUBMITTED) {
            throw ValidationException::withMessages(['status' => 'Transfer hanya bisa disetujui dari status SUBMITTED.']);
        }

        DB::transaction(function () use ($transfer, $userId): void {
            $transfer->load(['items.item.baseUnit']);
            $tenantId  = (int) $transfer->tenant_id;
            $now       = Carbon::now();

            foreach ($transfer->items as $tItem) {
                $itemId     = (int) $tItem->item_id;
                $baseUnitId = (int) ($tItem->item?->base_unit_id ?? $tItem->unit_id);
                $qty        = (string) $tItem->qty_in_base_unit;

                // Lock saldo asal
                $fromBalance = StockBalance::query()
                    ->where('tenant_id', $tenantId)
                    ->where('outlet_id', $transfer->from_outlet_id)
                    ->where('item_id', $itemId)
                    ->where('stock_target', StockMutation::TARGET_OUTLET_WAREHOUSE)
                    ->lockForUpdate()
                    ->first();

                $fromQty = (string) ($fromBalance?->qty_on_hand ?? '0.000000');

                if (bccomp($fromQty, $qty, 6) < 0) {
                    $name = $tItem->item?->name ?? "Item #{$itemId}";
                    throw ValidationException::withMessages([
                        'stock' => "Stok {$name} di outlet asal tidak mencukupi (tersedia: {$fromQty}, diperlukan: {$qty}).",
                    ]);
                }

                $newFromQty = bcsub($fromQty, $qty, 6);

                // TRANSFER_OUT: kurangi saldo asal
                StockMutation::query()->create([
                    'tenant_id'      => $tenantId,
                    'outlet_id'      => $transfer->from_outlet_id,
                    'item_id'        => $itemId,
                    'stock_target'   => StockMutation::TARGET_OUTLET_WAREHOUSE,
                    'unit_id'        => $baseUnitId,
                    'mutation_type'  => StockMutation::TYPE_TRANSFER_OUT,
                    'qty_change'     => bcmul($qty, '-1', 6),
                    'balance_after'  => $newFromQty,
                    'reference_type' => StockTransfer::class,
                    'reference_id'   => $transfer->id,
                    'performed_by'   => $userId,
                    'performed_at'   => $now,
                    'notes'          => "Transfer ke outlet #{$transfer->to_outlet_id}",
                ]);

                if ($fromBalance) {
                    $fromBalance->forceFill([
                        'qty_on_hand'      => $newFromQty,
                        'last_mutation_at' => $now,
                    ])->save();
                }

                // Lock saldo tujuan
                $toBalance = StockBalance::query()
                    ->where('tenant_id', $tenantId)
                    ->where('outlet_id', $transfer->to_outlet_id)
                    ->where('item_id', $itemId)
                    ->where('stock_target', StockMutation::TARGET_OUTLET_WAREHOUSE)
                    ->lockForUpdate()
                    ->first();

                $toQty    = (string) ($toBalance?->qty_on_hand ?? '0.000000');
                $newToQty = bcadd($toQty, $qty, 6);

                // TRANSFER_IN: tambah saldo tujuan
                StockMutation::query()->create([
                    'tenant_id'      => $tenantId,
                    'outlet_id'      => $transfer->to_outlet_id,
                    'item_id'        => $itemId,
                    'stock_target'   => StockMutation::TARGET_OUTLET_WAREHOUSE,
                    'unit_id'        => $baseUnitId,
                    'mutation_type'  => StockMutation::TYPE_TRANSFER_IN,
                    'qty_change'     => $qty,
                    'balance_after'  => $newToQty,
                    'reference_type' => StockTransfer::class,
                    'reference_id'   => $transfer->id,
                    'performed_by'   => $userId,
                    'performed_at'   => $now,
                    'notes'          => "Transfer dari outlet #{$transfer->from_outlet_id}",
                ]);

                if ($toBalance) {
                    $toBalance->forceFill([
                        'qty_on_hand'      => $newToQty,
                        'last_mutation_at' => $now,
                    ])->save();
                } else {
                    StockBalance::query()->create([
                        'tenant_id'      => $tenantId,
                        'outlet_id'      => $transfer->to_outlet_id,
                        'item_id'        => $itemId,
                        'stock_target'   => StockMutation::TARGET_OUTLET_WAREHOUSE,
                        'qty_on_hand'    => $newToQty,
                        'avg_cost'       => '0.0000',
                        'total_value'    => '0.0000',
                        'last_mutation_at' => $now,
                    ]);
                }
            }

            $transfer->forceFill([
                'status'      => StockTransfer::STATUS_APPROVED,
                'approved_by' => $userId,
                'approved_at' => $now,
            ])->save();
        });

        return $transfer->fresh();
    }

    public function reject(StockTransfer $transfer, int $userId, string $reason): StockTransfer
    {
        if ($transfer->status !== StockTransfer::STATUS_SUBMITTED) {
            throw ValidationException::withMessages(['status' => 'Transfer hanya bisa ditolak dari status SUBMITTED.']);
        }

        $transfer->forceFill([
            'status'           => StockTransfer::STATUS_REJECTED,
            'rejected_by'      => $userId,
            'rejected_at'      => Carbon::now(),
            'rejection_reason' => $reason,
        ])->save();

        return $transfer->fresh();
    }

    public function void(StockTransfer $transfer, int $userId, string $reason): StockTransfer
    {
        if ($transfer->status !== StockTransfer::STATUS_APPROVED) {
            throw ValidationException::withMessages(['status' => 'Hanya transfer APPROVED yang bisa dibatalkan.']);
        }

        DB::transaction(function () use ($transfer, $userId, $reason): void {
            $ledger = app(StockLedgerService::class);

            $mutations = StockMutation::query()
                ->where('reference_type', StockTransfer::class)
                ->where('reference_id', $transfer->id)
                ->whereIn('mutation_type', [StockMutation::TYPE_TRANSFER_OUT, StockMutation::TYPE_TRANSFER_IN])
                ->get();

            foreach ($mutations as $mutation) {
                $ledger->voidMutation($mutation, [
                    'performed_by' => $userId,
                    'void_reason'  => $reason,
                ]);
            }

            $transfer->forceFill([
                'status'      => StockTransfer::STATUS_VOIDED,
                'voided_by'   => $userId,
                'voided_at'   => Carbon::now(),
                'void_reason' => $reason,
            ])->save();
        });

        return $transfer->fresh();
    }
}
