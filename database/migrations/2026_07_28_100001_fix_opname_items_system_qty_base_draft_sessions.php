<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Perbaiki system_qty_base pada opname_items yang masih DRAFT,
 * di mana system_qty_base = 0 padahal stok live > 0.
 *
 * Root cause: systemQty() dulu hanya query stock_target = OUTLET_DAILY.
 * Jika stok tersimpan di target lain, snapshot saat sesi mulai menjadi 0.
 * Akibatnya selisih = 0 - fisik = negatif, bukan sistem - fisik yang benar.
 */
return new class extends Migration
{
    public function up(): void
    {
        $zeroItems = DB::table('opname_items')
            ->join('opname_sessions', 'opname_sessions.id', '=', 'opname_items.opname_session_id')
            ->where('opname_sessions.status', 'DRAFT')
            ->where(function ($q): void {
                $q->whereRaw('CAST(opname_items.system_qty_base AS DECIMAL(18,6)) = 0')
                  ->orWhereNull('opname_items.system_qty_base');
            })
            ->select([
                'opname_items.id',
                'opname_items.tenant_id',
                'opname_items.item_id',
                'opname_items.is_counted',
                'opname_items.physical_qty_base',
                'opname_sessions.outlet_id',
            ])
            ->get();

        foreach ($zeroItems as $row) {
            $liveQty = (float) DB::table('stock_balances')
                ->where('tenant_id', $row->tenant_id)
                ->where('outlet_id', $row->outlet_id)
                ->where('item_id', $row->item_id)
                ->sum('qty_on_hand');

            if ($liveQty <= 0) {
                continue;
            }

            $sysQtyFixed = number_format($liveQty, 6, '.', '');

            if ($row->is_counted) {
                // Item sudah dihitung — recalculate variance = system - physical
                $variance = number_format($liveQty - (float) $row->physical_qty_base, 6, '.', '');
                DB::table('opname_items')->where('id', $row->id)->update([
                    'system_qty'      => $sysQtyFixed,
                    'system_qty_base' => $sysQtyFixed,
                    'variance_qty'    => $variance,
                    'variance'        => $variance,
                ]);
            } else {
                // Belum dihitung — variance = system (belum ada fisik)
                DB::table('opname_items')->where('id', $row->id)->update([
                    'system_qty'      => $sysQtyFixed,
                    'system_qty_base' => $sysQtyFixed,
                    'variance_qty'    => $sysQtyFixed,
                    'variance'        => $sysQtyFixed,
                ]);
            }
        }
    }

    public function down(): void
    {
        // Tidak reversible — data asli tidak di-backup di migration ini
    }
};
