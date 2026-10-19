<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_count_template_items', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('template_id');
            // item_id merujuk ke master Item existing -- SENGAJA tidak ada
            // pencocokan by-name (lihat temuan audit Excel: carry-forward by
            // nama item di form kertas asli rapuh terhadap typo).
            $table->unsignedBigInteger('item_id');
            // Snapshot label subkategori (mis. "COFFEE & TEA") -- independen
            // dari item_categories/department_item_categories supaya template
            // ini stabil walau mapping master berubah di kemudian hari.
            $table->string('subcategory_label', 100)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            // Subset dari inventory_count_templates.locations yang relevan
            // untuk item ini -- "Template satu lokasi cukup satu bagian;
            // jangan memaksa semua item memiliki Gudang + Area jika belum
            // dikonfirmasi" (instruksi eksplisit).
            $table->json('locations');
            // Niat FORM (apakah form kertas minta Utuh+Ecer terpisah untuk
            // item ini) -- validitas konfigurasi sungguhan (apakah item
            // master punya inventory_unit/ratio yang valid) baru dicek saat
            // baris dokumen dibuat, lihat InventoryCountLine.config_valid.
            $table->boolean('requires_conversion')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['template_id', 'item_id'], 'uq_inv_count_template_items_item');
            $table->index(['tenant_id', 'template_id', 'sort_order'], 'idx_inv_count_template_items_order');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_count_template_items');
    }
};
