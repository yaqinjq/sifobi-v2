<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 203 item kg→gr di-import dengan inventory_ratio=1 (default fallback).
        // Ratio yang benar adalah 1000 (1 kg = 1000 gr).
        $kgId = DB::table('units')->where('abbreviation', 'kg')->value('id');
        $grId = DB::table('units')->where('abbreviation', 'gr')->value('id');

        if (! $kgId || ! $grId) {
            return;
        }

        DB::table('items')
            ->where('inventory_unit_id', $kgId)
            ->where('base_unit_id', $grId)
            ->where('inventory_ratio', 1)
            ->update(['inventory_ratio' => 1000]);
    }

    public function down(): void
    {
        $kgId = DB::table('units')->where('abbreviation', 'kg')->value('id');
        $grId = DB::table('units')->where('abbreviation', 'gr')->value('id');

        if (! $kgId || ! $grId) {
            return;
        }

        DB::table('items')
            ->where('inventory_unit_id', $kgId)
            ->where('base_unit_id', $grId)
            ->where('inventory_ratio', 1000)
            ->update(['inventory_ratio' => 1]);
    }
};
