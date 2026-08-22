<?php

namespace App\Services;

use App\Modules\Core\Models\Outlet;
use App\Modules\Pos\Models\PosOrder;
use App\Modules\Pos\Models\PosOrderItem;
use App\Modules\Pos\Models\PosPayment;
use App\Modules\Pos\Models\PosTable;
use App\Modules\Production\Models\Menu;
use App\Support\Decimal;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PosOrderService
{
    /**
     * @param  array{outlet_id: int, order_type: string, pos_table_id?: int|null, notes?: string|null}  $data
     */
    public function openOrder(array $data, int $tenantId, int $userId): PosOrder
    {
        return DB::transaction(function () use ($data, $tenantId, $userId): PosOrder {
            $outletId = (int) $data['outlet_id'];
            $tableId = $data['pos_table_id'] ?? null;

            if ($data['order_type'] === PosOrder::TYPE_DINE_IN) {
                if (! $tableId) {
                    throw ValidationException::withMessages(['pos_table_id' => 'Pilih meja untuk order dine-in.']);
                }

                $table = PosTable::query()->lockForUpdate()->findOrFail($tableId);

                if ($table->status !== PosTable::STATUS_AVAILABLE) {
                    throw ValidationException::withMessages(['pos_table_id' => 'Meja ini sedang tidak tersedia.']);
                }

                $table->update(['status' => PosTable::STATUS_OCCUPIED]);
            } else {
                $tableId = null;
            }

            return PosOrder::query()->create([
                'tenant_id'    => $tenantId,
                'outlet_id'    => $outletId,
                'pos_table_id' => $tableId,
                'order_number' => $this->generateOrderNumber($tenantId, $outletId),
                'order_type'   => $data['order_type'],
                'status'       => PosOrder::STATUS_OPEN,
                'notes'        => $data['notes'] ?? null,
                'created_by'   => $userId,
                'opened_at'    => now(),
            ]);
        });
    }

    /**
     * @param  array{menu_id: int, qty: mixed, notes?: string|null}  $data
     */
    public function addItem(PosOrder $order, array $data): PosOrder
    {
        return DB::transaction(function () use ($order, $data): PosOrder {
            $order = PosOrder::query()->lockForUpdate()->findOrFail($order->id);

            if (! $order->canAddItem()) {
                throw ValidationException::withMessages(['status' => 'Order ini sudah tidak bisa diubah.']);
            }

            $menu = Menu::query()->findOrFail($data['menu_id']);
            $qty = Decimal::toFixed($data['qty'] ?? 1, 4);
            $unitPrice = Decimal::toFixed($menu->selling_price ?? 0, 4);

            $order->items()->create([
                'tenant_id'  => $order->tenant_id,
                'menu_id'    => $menu->id,
                'item_name'  => $menu->name,
                'unit_price' => $unitPrice,
                'qty'        => $qty,
                'subtotal'   => bcmul($unitPrice, $qty, 4),
                'notes'      => $data['notes'] ?? null,
                'sort_order' => $order->items()->count(),
            ]);

            $order->refresh();
            $order->recalculateTotals();
            $order->save();

            return $order->refresh()->load('items.menu');
        });
    }

    public function removeItem(PosOrder $order, PosOrderItem $item): PosOrder
    {
        return DB::transaction(function () use ($order, $item): PosOrder {
            $order = PosOrder::query()->lockForUpdate()->findOrFail($order->id);

            if (! $order->canAddItem()) {
                throw ValidationException::withMessages(['status' => 'Order ini sudah tidak bisa diubah.']);
            }

            $item->delete();

            $order->refresh();
            $order->recalculateTotals();
            $order->save();

            return $order->refresh()->load('items.menu');
        });
    }

    /**
     * @param  array{discount_amount?: mixed, tax_amount?: mixed, service_charge_amount?: mixed}  $data
     */
    public function checkout(PosOrder $order, array $data): PosOrder
    {
        return DB::transaction(function () use ($order, $data): PosOrder {
            $order = PosOrder::query()->lockForUpdate()->findOrFail($order->id);

            if (! $order->canCheckout()) {
                throw ValidationException::withMessages(['status' => 'Order belum ada item / sudah tidak bisa checkout.']);
            }

            $order->discount_amount = Decimal::toFixed($data['discount_amount'] ?? 0, 4);
            $order->tax_amount = Decimal::toFixed($data['tax_amount'] ?? 0, 4);
            $order->service_charge_amount = Decimal::toFixed($data['service_charge_amount'] ?? 0, 4);
            $order->recalculateTotals();
            $order->save();

            return $order->refresh()->load('items.menu');
        });
    }

    /**
     * @param  array{method: string, amount: mixed, reference_no?: string|null}  $data
     */
    public function recordPayment(PosOrder $order, array $data, int $userId): PosOrder
    {
        return DB::transaction(function () use ($order, $data, $userId): PosOrder {
            $order = PosOrder::query()->lockForUpdate()->findOrFail($order->id);

            if ($order->status !== PosOrder::STATUS_OPEN) {
                throw ValidationException::withMessages(['status' => 'Order ini sudah lunas/dibatalkan.']);
            }

            PosPayment::query()->create([
                'tenant_id'     => $order->tenant_id,
                'pos_order_id'  => $order->id,
                'method'        => $data['method'],
                'amount'        => Decimal::toFixed($data['amount'], 4),
                'reference_no'  => $data['reference_no'] ?? null,
                'created_by'    => $userId,
                'paid_at'       => now(),
            ]);

            $totalPaid = $order->fresh()->amountPaid();

            if (bccomp($totalPaid, (string) $order->total_amount, 4) >= 0) {
                $order->update(['status' => PosOrder::STATUS_PAID, 'closed_at' => now()]);

                if ($order->pos_table_id) {
                    PosTable::query()->where('id', $order->pos_table_id)->update(['status' => PosTable::STATUS_AVAILABLE]);
                }
            }

            return $order->refresh()->load(['items.menu', 'payments']);
        });
    }

    public function void(PosOrder $order, string $reason = ''): PosOrder
    {
        return DB::transaction(function () use ($order, $reason): PosOrder {
            $order = PosOrder::query()->lockForUpdate()->findOrFail($order->id);

            if (! $order->canVoid()) {
                throw ValidationException::withMessages(['status' => 'Order ini tidak bisa dibatalkan pada status saat ini.']);
            }

            $order->update([
                'status'    => PosOrder::STATUS_VOID,
                'notes'     => trim($order->notes."\n[Dibatalkan] ".$reason),
                'closed_at' => now(),
            ]);

            if ($order->pos_table_id) {
                PosTable::query()->where('id', $order->pos_table_id)->update(['status' => PosTable::STATUS_AVAILABLE]);
            }

            return $order->refresh();
        });
    }

    private function generateOrderNumber(int $tenantId, int $outletId): string
    {
        $outlet = Outlet::withoutGlobalScopes()->find($outletId);
        $outletCode = $outlet?->code ?: 'OUT';
        $ymd = Carbon::now()->format('ymd');
        $prefix = "POS-{$outletCode}-{$ymd}-";

        $latest = PosOrder::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('order_number', 'like', $prefix.'%')
            ->lockForUpdate()
            ->orderByDesc('order_number')
            ->value('order_number');

        $next = $latest ? ((int) substr((string) $latest, -4)) + 1 : 1;

        return $prefix.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }
}
