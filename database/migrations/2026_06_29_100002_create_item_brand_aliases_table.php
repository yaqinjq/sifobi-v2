<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('item_brand_aliases')) {
            return;
        }

        Schema::create('item_brand_aliases', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')
                ->constrained(indexName: 'fk_item_brand_alias_tenant')
                ->cascadeOnDelete();
            $table->foreignId('item_id')
                ->constrained(indexName: 'fk_item_brand_alias_item')
                ->cascadeOnDelete();
            $table->foreignId('brand_id')
                ->constrained(indexName: 'fk_item_brand_alias_brand')
                ->restrictOnDelete();
            $table->string('brand_sku', 100);
            $table->string('brand_item_name')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->unique(['tenant_id', 'brand_id', 'brand_sku'], 'uq_item_brand_alias_sku');
            $table->index(['tenant_id', 'item_id'], 'idx_item_brand_alias_item');
            $table->index(['tenant_id', 'brand_id'], 'idx_item_brand_alias_brand');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('item_brand_aliases');
    }
};
