<?php

namespace App\Services;

use App\Modules\Core\Models\Outlet;
use App\Modules\Pos\Models\PosOrder;
use App\Modules\Pos\Models\PosOrderItem;
use App\Modules\Pos\Models\PosPayment;
use App\Modules\Pos\Models\PosTable;
use App\Modules\Production\Models\Menu;
use App\Modules\Production\Models\RecipeOutlet;
use App\Modules\Stock\Models\StockMutation;
use App\Support\Decimal;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PosOrderService
{
    public function __construct(
        private readonly StockLedgerService $stockLedgerService,
        private readonly PosShiftService $shiftService,
    ) {}

    /**
     * @param  array{outlet_id: int, order_type: string, pos_table_id?: int|null, notes?: string|null}  $data
     */
    public function openOrder(array $data, int $tenantId, ?int $userId = null): PosOrder
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
            $notes = $data['notes'] ?? null;

            // Tap menu yang sama berkali-kali harus nambah qty di baris yang
            // sama (pola semua mesin kasir modern), BUKAN bikin baris baru
            // terus-terusan. Cuma digabung kalau baris lama masih PENDING --
            // kalau sudah ditandai READY di dapur, qty tambahan itu order
            // baru (ronde ulang), harus baris baru sendiri.
            $existing = $notes ? null : $order->items()
                ->where('menu_id', $menu->id)
                ->where('status', PosOrderItem::STATUS_PENDING)
                ->whereNull('notes')
                ->latest('id')
                ->first();

            if ($existing) {
                $newQty = bcadd((string) $existing->qty, $qty, 4);
                $existing->update([
                    'qty' => $newQty,
                    'subtotal' => bcmul($unitPrice, $newQty, 4),
                ]);
            } else {
                $order->items()->create([
                    'tenant_id'  => $order->tenant_id,
                    'menu_id'    => $menu->id,
                    'item_name'  => $menu->name,
                    'unit_price' => $unitPrice,
                    'qty'        => $qty,
                    'subtotal'   => bcmul($unitPrice, $qty, 4),
                    'notes'      => $notes,
                    'sort_order' => $order->items()->count(),
                ]);
            }

            $order->refresh();
            $order->recalculateTotals();
            $order->save();

            return $order->refresh()->load('items.menu');
        });
    }

    public function updateItemQty(PosOrder $order, PosOrderItem $item, string $qty): PosOrder
    {
        return DB::transaction(function () use ($order, $item, $qty): PosOrder {
            $order = PosOrder::query()->lockForUpdate()->findOrFail($order->id);

            if (! $order->canAddItem()) {
                throw ValidationException::withMessages(['status' => 'Order ini sudah tidak bisa diubah.']);
            }

            $qty = Decimal::toFixed($qty, 4);

            if (bccomp($qty, '0', 4) <= 0) {
                $item->delete();
            } else {
                $item->update([
                    'qty' => $qty,
                    'subtotal' => bcmul((string) $item->unit_price, $qty, 4),
                ]);
            }

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

            $shift = $this->shiftService->findOpenShift((int) $order->tenant_id, (int) $order->outlet_id);

            if (! $shift) {
                throw ValidationException::withMessages(['shift' => 'Belum ada shift kasir yang dibuka untuk outlet ini.']);
            }

            // Tender bisa lebih besar dari sisa tagihan (mis. bayar tunai
            // Rp100rb untuk tagihan Rp60rb, kembalian Rp40rb). Yang dicatat
            // di ledger pembayaran & yang masuk hitungan kas shift HARUS
            // cuma bagian yang benar-benar melunasi tagihan, bukan tender
            // kotornya -- kalau tidak, rekonsiliasi kas shift jadi salah
            // hitung (menganggap uang kembalian ikut masuk laci).
            $tendered = Decimal::toFixed($data['amount'], 4);
            $remainingDue = bcsub((string) $order->total_amount, $order->amountPaid(), 4);
            $amountApplied = bccomp($tendered, $remainingDue, 4) > 0 ? $remainingDue : $tendered;

            PosPayment::query()->create([
                'tenant_id'     => $order->tenant_id,
                'pos_order_id'  => $order->id,
                'pos_shift_id'  => $shift->id,
                'method'        => $data['method'],
                'amount'        => $amountApplied,
                'reference_no'  => $data['reference_no'] ?? null,
                'created_by'    => $userId,
                'paid_at'       => now(),
            ]);

            $totalPaid = $order->fresh()->amountPaid();

            if (bccomp($totalPaid, (string) $order->total_amount, 4) >= 0) {
                $this->deductStockForOrder($order, $userId);

                $order->update(['status' => PosOrder::STATUS_PAID, 'closed_at' => now()]);

                if ($order->pos_table_id) {
                    PosTable::query()->where('id', $order->pos_table_id)->update(['status' => PosTable::STATUS_AVAILABLE]);
                }
            }

            return $order->refresh()->load(['items.menu', 'payments']);
        });
    }

    /**
     * Pindahkan sebagian atau seluruh qty 1 baris item ke order lain yang
     * sudah OPEN di outlet yang sama. Kedua order harus belum ada
     * pembayaran sama sekali (lihat PosOrder::canSplitOrMerge()) supaya
     * tidak perlu rekonsiliasi uang yang sudah terlanjur dibayar.
     *
     * @return array{source: PosOrder, target: PosOrder}
     */
    public function splitItem(PosOrderItem $item, PosOrder $targetOrder, string $qty): array
    {
        return DB::transaction(function () use ($item, $targetOrder, $qty): array {
            $sourceId = (int) $item->pos_order_id;
            $targetId = (int) $targetOrder->id;

            if ($sourceId === $targetId) {
                throw ValidationException::withMessages(['target_order_id' => 'Order tujuan harus berbeda dari order asal.']);
            }

            [$firstId, $secondId] = $sourceId < $targetId ? [$sourceId, $targetId] : [$targetId, $sourceId];
            $locked = PosOrder::query()->whereIn('id', [$firstId, $secondId])->lockForUpdate()->get()->keyBy('id');

            $source = $locked->get($sourceId);
            $target = $locked->get($targetId);
            $item = PosOrderItem::query()->lockForUpdate()->findOrFail($item->id);

            if ((int) $source->outlet_id !== (int) $target->outlet_id) {
                throw ValidationException::withMessages(['target_order_id' => 'Order tujuan harus di outlet yang sama.']);
            }

            if (! $source->canSplitOrMerge() || ! $target->canSplitOrMerge()) {
                throw ValidationException::withMessages(['status' => 'Order asal/tujuan sudah tidak bisa di-split (sudah lunas/ada pembayaran).']);
            }

            $qty = Decimal::toFixed($qty, 4);

            if (bccomp($qty, '0', 4) <= 0 || bccomp($qty, (string) $item->qty, 4) > 0) {
                throw ValidationException::withMessages(['qty' => 'Jumlah yang dipindah harus antara 0 dan qty baris ini.']);
            }

            if (bccomp($qty, (string) $item->qty, 4) === 0) {
                $item->update([
                    'pos_order_id' => $target->id,
                    'sort_order' => $target->items()->count(),
                ]);
            } else {
                $item->update([
                    'qty' => bcsub((string) $item->qty, $qty, 4),
                    'subtotal' => bcmul((string) $item->unit_price, bcsub((string) $item->qty, $qty, 4), 4),
                ]);

                $target->items()->create([
                    'tenant_id' => $target->tenant_id,
                    'menu_id' => $item->menu_id,
                    'item_name' => $item->item_name,
                    'unit_price' => $item->unit_price,
                    'qty' => $qty,
                    'subtotal' => bcmul((string) $item->unit_price, $qty, 4),
                    'notes' => $item->notes,
                    'sort_order' => $target->items()->count(),
                ]);
            }

            $source->refresh();
            $source->recalculateTotals();
            $source->save();

            $target->refresh();
            $target->recalculateTotals();
            $target->save();

            return [
                'source' => $source->refresh()->load('items.menu'),
                'target' => $target->refresh()->load('items.menu'),
            ];
        });
    }

    /**
     * Tarik semua item dari order sumber (OPEN, belum ada pembayaran) ke
     * order tujuan, lalu order sumber otomatis di-void (meja sumber balik
     * AVAILABLE kalau dine-in) -- pola status akhirnya sama seperti void(),
     * cuma catatannya beda supaya jelas ini hasil gabung, bukan pembatalan.
     */
    public function mergeOrders(PosOrder $target, PosOrder $source): PosOrder
    {
        return DB::transaction(function () use ($target, $source): PosOrder {
            if ((int) $target->id === (int) $source->id) {
                throw ValidationException::withMessages(['source_order_id' => 'Order sumber harus berbeda dari order tujuan.']);
            }

            [$firstId, $secondId] = $target->id < $source->id ? [$target->id, $source->id] : [$source->id, $target->id];
            $locked = PosOrder::query()->whereIn('id', [$firstId, $secondId])->lockForUpdate()->get()->keyBy('id');

            $target = $locked->get((int) $target->id);
            $source = $locked->get((int) $source->id);

            if ((int) $target->outlet_id !== (int) $source->outlet_id) {
                throw ValidationException::withMessages(['source_order_id' => 'Order sumber harus di outlet yang sama.']);
            }

            if (! $target->canSplitOrMerge() || ! $source->canSplitOrMerge()) {
                throw ValidationException::withMessages(['status' => 'Order sumber/tujuan sudah tidak bisa digabung (sudah lunas/ada pembayaran).']);
            }

            $source->items()->update(['pos_order_id' => $target->id]);

            $target->refresh();
            $target->recalculateTotals();
            $target->save();

            $source->update([
                'status' => PosOrder::STATUS_VOID,
                'notes' => trim($source->notes."\n[Digabung] ke #{$target->order_number}"),
                'closed_at' => now(),
            ]);

            if ($source->pos_table_id) {
                PosTable::query()->where('id', $source->pos_table_id)->update(['status' => PosTable::STATUS_AVAILABLE]);
            }

            return $target->refresh()->load(['items.menu', 'payments']);
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

    /**
     * Kurangi stok bahan baku per item order lewat resep yang sedang aktif
     * (di-assign) untuk menu itu di outlet ini. Kalau menu tidak punya
     * resep aktif di outlet tsb, item itu dilewati begitu saja (bukan
     * error) -- POS harus tetap bisa dipakai walau belum semua menu punya
     * resep siap. Kalau stok bahan tidak cukup, StockLedgerService::move()
     * akan throw ValidationException dan seluruh transaksi (termasuk
     * PosPayment yang baru dibuat) ikut rollback oleh caller.
     */
    private function deductStockForOrder(PosOrder $order, int $userId): void
    {
        $order->loadMissing('items');

        foreach ($order->items as $item) {
            $recipeOutlet = RecipeOutlet::query()
                ->where('outlet_id', $order->outlet_id)
                ->whereHas('recipe', fn ($q) => $q->where('menu_id', $item->menu_id))
                ->with('recipe.ingredients.item')
                ->first();

            if (! $recipeOutlet) {
                continue;
            }

            foreach ($recipeOutlet->recipe->ingredients as $ingredient) {
                if (! $ingredient->item) {
                    continue;
                }

                $qtyBase = bcmul($ingredient->recipeQtyBase(), (string) $item->qty, 6);

                if (bccomp($qtyBase, '0', 6) <= 0) {
                    continue;
                }

                $this->stockLedgerService->posSale([
                    'tenant_id' => $order->tenant_id,
                    'outlet_id' => $order->outlet_id,
                    'item_id' => $ingredient->item->id,
                    'unit_id' => $ingredient->item->base_unit_id,
                    'stock_target' => StockMutation::TARGET_OUTLET_DAILY,
                    'qty_change' => $qtyBase,
                    'reference_type' => PosOrder::class,
                    'reference_id' => $order->id,
                    'performed_by' => $userId,
                    'performed_at' => now(),
                    'notes' => "Penjualan POS #{$order->order_number} - {$item->item_name}",
                    'metadata' => [
                        'pos_order_item_id' => $item->id,
                        'menu_id' => $item->menu_id,
                        'recipe_id' => $recipeOutlet->recipe->id,
                    ],
                ]);
            }
        }
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
