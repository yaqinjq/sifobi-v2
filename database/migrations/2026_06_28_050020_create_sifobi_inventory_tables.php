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
        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained(indexName: 'fk_units_tenant')->cascadeOnDelete();
            $table->string('code', 24);
            $table->string('name');
            $table->unsignedTinyInteger('decimal_places')->default(3);
            $table->string('status', 24)->default('ACTIVE')->index('idx_units_status');
            $table->timestamps();

            $table->unique(['tenant_id', 'code'], 'uq_units_tenant_code');
        });

        Schema::create('item_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained(indexName: 'fk_item_categories_tenant')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained(table: 'item_categories', indexName: 'fk_item_categories_parent')->nullOnDelete();
            $table->string('code', 32);
            $table->string('name');
            $table->string('status', 24)->default('ACTIVE')->index('idx_item_categories_status');
            $table->timestamps();

            $table->unique(['tenant_id', 'code'], 'uq_item_categories_tenant_code');
        });

        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained(indexName: 'fk_items_tenant')->cascadeOnDelete();
            $table->foreignId('item_category_id')->nullable()->constrained(indexName: 'fk_items_category')->nullOnDelete();
            $table->foreignId('inventory_unit_id')->constrained(table: 'units', indexName: 'fk_items_inventory_unit')->restrictOnDelete();
            $table->foreignId('purchase_unit_id')->nullable()->constrained(table: 'units', indexName: 'fk_items_purchase_unit')->nullOnDelete();
            $table->string('canonical_sku', 64);
            $table->string('name');
            $table->string('item_type', 32)->default('STOCK');
            $table->string('barcode', 128)->nullable();
            $table->decimal('standard_cost', 19, 4)->default(0);
            $table->boolean('track_stock')->default(true);
            $table->boolean('is_active')->default(true)->index('idx_items_active');
            $table->timestamps();

            $table->unique(['tenant_id', 'canonical_sku'], 'uq_items_tenant_sku');
            $table->index(['tenant_id', 'item_category_id'], 'idx_items_tenant_category');
        });

        Schema::create('item_aliases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained(indexName: 'fk_item_aliases_tenant')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained(indexName: 'fk_item_aliases_item')->cascadeOnDelete();
            $table->foreignId('brand_id')->nullable()->constrained(indexName: 'fk_item_aliases_brand')->nullOnDelete();
            $table->foreignId('outlet_id')->nullable()->constrained(indexName: 'fk_item_aliases_outlet')->nullOnDelete();
            $table->string('source_system', 48)->default('LEGACY');
            $table->string('alias_type', 32)->default('SKU');
            $table->string('alias_code', 128);
            $table->string('alias_name')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'source_system', 'alias_type', 'alias_code'], 'uq_item_alias_lookup');
            $table->index(['tenant_id', 'item_id'], 'idx_item_aliases_item');
        });

        Schema::create('item_outlets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained(indexName: 'fk_item_outlets_tenant')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained(indexName: 'fk_item_outlets_item')->cascadeOnDelete();
            $table->foreignId('outlet_id')->constrained(indexName: 'fk_item_outlets_outlet')->cascadeOnDelete();
            $table->decimal('min_stock', 18, 6)->nullable();
            $table->decimal('max_stock', 18, 6)->nullable();
            $table->decimal('par_stock', 18, 6)->nullable();
            $table->boolean('is_active')->default(true)->index('idx_item_outlets_active');
            $table->timestamps();

            $table->unique(['tenant_id', 'item_id', 'outlet_id'], 'uq_item_outlets_scope');
        });

        Schema::create('item_department_maps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained(indexName: 'fk_item_dept_maps_tenant')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained(indexName: 'fk_item_dept_maps_item')->cascadeOnDelete();
            $table->foreignId('department_id')->constrained(indexName: 'fk_item_dept_maps_department')->cascadeOnDelete();
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->unique(['tenant_id', 'item_id', 'department_id'], 'uq_item_dept_maps_scope');
        });

        Schema::create('unit_conversions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained(indexName: 'fk_unit_conversions_tenant')->cascadeOnDelete();
            $table->foreignId('item_id')->nullable()->constrained(indexName: 'fk_unit_conversions_item')->cascadeOnDelete();
            $table->foreignId('from_unit_id')->constrained(table: 'units', indexName: 'fk_unit_conversions_from_unit')->restrictOnDelete();
            $table->foreignId('to_unit_id')->constrained(table: 'units', indexName: 'fk_unit_conversions_to_unit')->restrictOnDelete();
            $table->decimal('multiply_rate', 18, 8);
            $table->timestamps();

            $table->unique(['tenant_id', 'item_id', 'from_unit_id', 'to_unit_id'], 'uq_unit_conversions_scope');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('unit_conversions');
        Schema::dropIfExists('item_department_maps');
        Schema::dropIfExists('item_outlets');
        Schema::dropIfExists('item_aliases');
        Schema::dropIfExists('items');
        Schema::dropIfExists('item_categories');
        Schema::dropIfExists('units');
    }
};
