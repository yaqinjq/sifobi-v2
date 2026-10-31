<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Simpan product_id OCIA (integer, STABIL -- tidak ikut berubah meski
 * product_code-nya diganti/di-standarisasi di sisi OCIA) terpisah dari
 * canonical_sku -- pola yang sama persis dengan wipro_product_code
 * (lihat migration add_wipro_product_code_to_items_table), supaya sync
 * katalog OCIA bisa mencocokkan item yang SAMA lintas sync meskipun
 * product_code-nya berubah, alih-alih membuat item baru (duplikat).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table): void {
            if (! Schema::hasColumn('items', 'ocia_product_id')) {
                $table->unsignedBigInteger('ocia_product_id')->nullable()->after('wipro_product_code');
                $table->index(['tenant_id', 'ocia_product_id'], 'idx_items_tenant_ocia_product_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table): void {
            if (Schema::hasColumn('items', 'ocia_product_id')) {
                $table->dropIndex('idx_items_tenant_ocia_product_id');
                $table->dropColumn('ocia_product_id');
            }
        });
    }
};
