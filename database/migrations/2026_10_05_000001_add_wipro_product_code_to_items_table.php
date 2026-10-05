<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Simpan kode produk Wipro ("Product_Code" di file katalog, mis. "KWIP0546")
 * terpisah dari canonical_sku -- supaya WiproCatalogImport bisa mencocokkan
 * item yang SAMA lintas upload meskipun SKU-nya berubah (mis. proyek
 * "STANDARISASI PRODUK WIP" dari pimpinan yang mengganti skema SKU lama ke
 * skema baru). Sebelumnya import cuma mencocokkan lewat canonical_sku --
 * begitu SKU sebuah produk di-standarisasi/diganti, import tidak mengenali
 * itu sebagai produk yang sama, jadi bikin ITEM BARU (duplikat) alih-alih
 * meng-update item lama yang sudah ada.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table): void {
            if (! Schema::hasColumn('items', 'wipro_product_code')) {
                $table->string('wipro_product_code', 64)->nullable()->after('canonical_sku');
                $table->index(['tenant_id', 'wipro_product_code'], 'idx_items_tenant_wipro_code');
            }
        });
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table): void {
            if (Schema::hasColumn('items', 'wipro_product_code')) {
                $table->dropIndex('idx_items_tenant_wipro_code');
                $table->dropColumn('wipro_product_code');
            }
        });
    }
};
