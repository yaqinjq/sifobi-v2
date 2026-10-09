<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_count_template_items', function (Blueprint $table): void {
            // Override unit per lokasi (json: {"GUDANG": unit_id, "BAR_AREA": unit_id}) --
            // untuk item yang form sumbernya genuinely beda unit per lokasi (mis. Simple
            // Syrup Gln di Gudang vs Kg di Bar Area). TIDAK ADA rasio konversi otomatis
            // antar unit berbeda substansi -- lihat InventoryCountService::resolveLineConfig(),
            // kalau override unit beda dari unit master item, config_valid dipaksa false
            // (qty_base tidak diklaim valid), staf tetap bisa isi angka mentah per lokasi.
            $table->json('location_units')->nullable()->after('locations');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_count_template_items', function (Blueprint $table): void {
            $table->dropColumn('location_units');
        });
    }
};
