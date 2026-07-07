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
        Schema::table('units', function (Blueprint $table) {
            if (! Schema::hasColumn('units', 'abbreviation')) {
                $table->string('abbreviation', 24)->nullable()->after('name');
                $table->index(['tenant_id', 'abbreviation'], 'idx_units_tenant_abbr');
            }
        });

        Schema::table('items', function (Blueprint $table) {
            if (! Schema::hasColumn('items', 'description')) {
                $table->text('description')->nullable()->after('name');
            }

            if (! Schema::hasColumn('items', 'purchase_ratio')) {
                $table->decimal('purchase_ratio', 18, 6)->nullable()->after('inventory_ratio');
            }

            if (! Schema::hasColumn('items', 'yield_pct')) {
                $table->decimal('yield_pct', 5, 2)->nullable()->after('purchase_ratio');
            }

            if (! Schema::hasColumn('items', 'last_purchase_price')) {
                $table->decimal('last_purchase_price', 19, 4)->nullable()->after('yield_pct');
            }
        });

        Schema::table('unit_conversions', function (Blueprint $table) {
            if (! Schema::hasColumn('unit_conversions', 'factor')) {
                $table->decimal('factor', 18, 8)->nullable()->after('multiply_rate');
            }
        });

        Schema::table('item_outlets', function (Blueprint $table) {
            if (! Schema::hasColumn('item_outlets', 'status')) {
                $table->string('status', 24)->default('ACTIVE')->after('outlet_id')->index('idx_item_outlets_status');
            }

            if (! Schema::hasColumn('item_outlets', 'opname_frequency')) {
                $table->string('opname_frequency', 24)->default('DAILY')->after('status');
            }

            if (! Schema::hasColumn('item_outlets', 'reorder_point')) {
                $table->decimal('reorder_point', 18, 6)->nullable()->after('par_stock');
            }

            if (! Schema::hasColumn('item_outlets', 'unit_id')) {
                $table->foreignId('unit_id')->nullable()->after('reorder_point')->constrained(table: 'units', indexName: 'fk_item_outlets_unit')->nullOnDelete();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('item_outlets', function (Blueprint $table) {
            if (Schema::hasColumn('item_outlets', 'unit_id')) {
                $table->dropForeign('fk_item_outlets_unit');
                $table->dropColumn('unit_id');
            }

            if (Schema::hasColumn('item_outlets', 'reorder_point')) {
                $table->dropColumn('reorder_point');
            }

            if (Schema::hasColumn('item_outlets', 'opname_frequency')) {
                $table->dropColumn('opname_frequency');
            }

            if (Schema::hasColumn('item_outlets', 'status')) {
                $table->dropIndex('idx_item_outlets_status');
                $table->dropColumn('status');
            }
        });

        Schema::table('unit_conversions', function (Blueprint $table) {
            if (Schema::hasColumn('unit_conversions', 'factor')) {
                $table->dropColumn('factor');
            }
        });

        Schema::table('items', function (Blueprint $table) {
            $columns = array_values(array_filter([
                Schema::hasColumn('items', 'description') ? 'description' : null,
                Schema::hasColumn('items', 'purchase_ratio') ? 'purchase_ratio' : null,
                Schema::hasColumn('items', 'yield_pct') ? 'yield_pct' : null,
                Schema::hasColumn('items', 'last_purchase_price') ? 'last_purchase_price' : null,
            ]));

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });

        Schema::table('units', function (Blueprint $table) {
            if (Schema::hasColumn('units', 'abbreviation')) {
                $table->dropIndex('idx_units_tenant_abbr');
                $table->dropColumn('abbreviation');
            }
        });
    }
};

