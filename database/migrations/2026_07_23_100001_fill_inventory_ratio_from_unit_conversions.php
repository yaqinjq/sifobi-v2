<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // Rasio default berdasarkan pasangan unit (from_abbr → to_abbr)
    // Dipakai jika unit_conversions tidak punya entri untuk pasangan tersebut
    private const KNOWN_RATIOS = [
        'kg-gr'  => 1000,
        'kg-mg'  => 1_000_000,
        'l-ml'   => 1000,
        'l-cl'   => 100,
        'l-dl'   => 10,
        'ltr-ml' => 1000,
    ];

    public function up(): void
    {
        // Step 1: item dengan unit sama → ratio = 1
        DB::table('items')
            ->whereColumn('inventory_unit_id', 'base_unit_id')
            ->where(fn ($q) => $q->whereNull('inventory_ratio')->orWhere('inventory_ratio', 0))
            ->update(['inventory_ratio' => 1]);

        // Step 2: item dengan unit berbeda dan ratio belum terisi
        $nullItems = DB::table('items as i')
            ->join('units as inv', 'inv.id', '=', 'i.inventory_unit_id')
            ->join('units as bas', 'bas.id', '=', 'i.base_unit_id')
            ->whereColumn('i.inventory_unit_id', '!=', 'i.base_unit_id')
            ->where(fn ($q) => $q->whereNull('i.inventory_ratio')->orWhere('i.inventory_ratio', 0))
            ->get([
                'i.id',
                'i.inventory_unit_id',
                'i.base_unit_id',
                'inv.abbreviation as inv_abbr',
                'bas.abbreviation as bas_abbr',
            ]);

        if ($nullItems->isEmpty()) {
            return;
        }

        // Ambil semua unit_conversions yang relevan dalam 1 query
        $invIds  = $nullItems->pluck('inventory_unit_id')->unique()->values();
        $basIds  = $nullItems->pluck('base_unit_id')->unique()->values();
        $itemIds = $nullItems->pluck('id')->values();

        $convRows = DB::table('unit_conversions')
            ->where(fn ($q) => $q->whereIn('item_id', $itemIds)->orWhereNull('item_id'))
            ->whereIn('from_unit_id', $invIds)
            ->whereIn('to_unit_id', $basIds)
            ->get(['item_id', 'from_unit_id', 'to_unit_id', 'multiply_rate']);

        foreach ($nullItems as $row) {
            // Prioritas: item-specific → global conversion → known-ratio map
            $specific = $convRows->first(fn ($c) => $c->item_id == $row->id
                && $c->from_unit_id == $row->inventory_unit_id
                && $c->to_unit_id == $row->base_unit_id);

            $global = $convRows->first(fn ($c) => is_null($c->item_id)
                && $c->from_unit_id == $row->inventory_unit_id
                && $c->to_unit_id == $row->base_unit_id);

            $conv = $specific ?? $global;

            if ($conv) {
                $ratio = (float) $conv->multiply_rate;
            } else {
                $pairKey = strtolower($row->inv_abbr) . '-' . strtolower($row->bas_abbr);
                $ratio   = self::KNOWN_RATIOS[$pairKey] ?? null;
            }

            if ($ratio && $ratio > 0) {
                DB::table('items')->where('id', $row->id)->update(['inventory_ratio' => $ratio]);
            }
        }
    }

    public function down(): void
    {
        // Tidak bisa di-rollback karena kita tidak tahu nilai aslinya
    }
};
