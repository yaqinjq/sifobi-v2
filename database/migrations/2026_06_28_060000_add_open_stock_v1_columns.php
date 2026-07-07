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
        Schema::table('items', function (Blueprint $table) {
            $table->foreignId('base_unit_id')->nullable()->after('purchase_unit_id')->constrained(table: 'units', indexName: 'fk_items_base_unit')->nullOnDelete();
            $table->decimal('inventory_ratio', 18, 6)->nullable()->after('base_unit_id');
        });

        Schema::table('stock_mutations', function (Blueprint $table) {
            $table->string('stock_target', 32)->default('OUTLET_DAILY')->after('item_id');
            $table->index(['tenant_id', 'outlet_id', 'item_id', 'stock_target'], 'idx_stock_mutations_target');
        });

        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            Schema::table('stock_balances', function (Blueprint $table) {
                $table->dropForeign('fk_stock_balances_tenant');
                $table->dropForeign('fk_stock_balances_outlet');
                $table->dropForeign('fk_stock_balances_item');
            });

            Schema::table('stock_balances', function (Blueprint $table) {
                $table->dropUnique('uq_stock_balances_scope');
            });
        }

        Schema::table('stock_balances', function (Blueprint $table) {
            $table->string('stock_target', 32)->default('OUTLET_DAILY')->after('item_id');

            if (Schema::getConnection()->getDriverName() !== 'sqlite') {
                $table->unique(['tenant_id', 'outlet_id', 'item_id', 'stock_target'], 'uq_stock_balances_scope');
            }
        });

        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            Schema::table('stock_balances', function (Blueprint $table) {
                $table->foreign('tenant_id', 'fk_stock_balances_tenant')->references('id')->on('tenants')->cascadeOnDelete();
                $table->foreign('outlet_id', 'fk_stock_balances_outlet')->references('id')->on('outlets')->restrictOnDelete();
                $table->foreign('item_id', 'fk_stock_balances_item')->references('id')->on('items')->restrictOnDelete();
            });
        }

        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            Schema::table('open_stocks', function (Blueprint $table) {
                $table->dropForeign('fk_open_stocks_tenant');
                $table->dropForeign('fk_open_stocks_outlet');
                $table->dropForeign('fk_open_stocks_department');
            });
        }

        Schema::table('open_stocks', function (Blueprint $table) {
            $table->dropUnique('uq_open_stocks_scope');
        });

        Schema::table('open_stocks', function (Blueprint $table) {
            $table->foreignId('item_id')->nullable()->after('department_id')->constrained(indexName: 'fk_open_stocks_item')->restrictOnDelete();
            $table->foreignId('unit_id')->nullable()->after('item_id')->constrained(table: 'units', indexName: 'fk_open_stocks_unit')->restrictOnDelete();
            $table->string('stock_target', 32)->default('OUTLET_DAILY')->after('unit_id');
            $table->decimal('qty_whole', 18, 6)->default(0)->after('business_date');
            $table->decimal('qty_loose', 18, 6)->default(0)->after('qty_whole');
            $table->decimal('qty_in_base_unit', 18, 6)->default(0)->after('qty_loose');
            $table->decimal('cost_per_unit', 19, 4)->nullable()->after('qty_in_base_unit');
            $table->foreignId('created_by')->nullable()->after('status')->constrained(table: 'users', indexName: 'fk_open_stocks_created_by')->nullOnDelete();
            $table->foreignId('mutation_id')->nullable()->after('posted_at')->constrained(table: 'stock_mutations', indexName: 'fk_open_stocks_mutation')->nullOnDelete();
            $table->index(['tenant_id', 'outlet_id', 'item_id', 'stock_target', 'business_date'], 'idx_open_stocks_lookup');
            $table->index(['tenant_id', 'status', 'business_date'], 'idx_open_stocks_status_date');
        });

        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            Schema::table('open_stocks', function (Blueprint $table) {
                $table->foreign('tenant_id', 'fk_open_stocks_tenant')->references('id')->on('tenants')->cascadeOnDelete();
                $table->foreign('outlet_id', 'fk_open_stocks_outlet')->references('id')->on('outlets')->restrictOnDelete();
                $table->foreign('department_id', 'fk_open_stocks_department')->references('id')->on('departments')->nullOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('open_stocks', function (Blueprint $table) {
            $table->dropForeign('fk_open_stocks_item');
            $table->dropForeign('fk_open_stocks_unit');
            $table->dropForeign('fk_open_stocks_created_by');
            $table->dropForeign('fk_open_stocks_mutation');
            $table->dropIndex('idx_open_stocks_lookup');
            $table->dropIndex('idx_open_stocks_status_date');
            $table->dropColumn([
                'item_id',
                'unit_id',
                'stock_target',
                'qty_whole',
                'qty_loose',
                'qty_in_base_unit',
                'cost_per_unit',
                'created_by',
                'mutation_id',
            ]);

            if (Schema::getConnection()->getDriverName() !== 'sqlite') {
                $table->unique(['tenant_id', 'outlet_id', 'department_id', 'business_date'], 'uq_open_stocks_scope');
            }
        });

        Schema::table('stock_balances', function (Blueprint $table) {
            if (Schema::getConnection()->getDriverName() !== 'sqlite') {
                $table->dropForeign('fk_stock_balances_tenant');
                $table->dropForeign('fk_stock_balances_outlet');
                $table->dropForeign('fk_stock_balances_item');
                $table->dropUnique('uq_stock_balances_scope');
            }

            $table->dropColumn('stock_target');

            if (Schema::getConnection()->getDriverName() !== 'sqlite') {
                $table->unique(['tenant_id', 'outlet_id', 'item_id'], 'uq_stock_balances_scope');
                $table->foreign('tenant_id', 'fk_stock_balances_tenant')->references('id')->on('tenants')->cascadeOnDelete();
                $table->foreign('outlet_id', 'fk_stock_balances_outlet')->references('id')->on('outlets')->restrictOnDelete();
                $table->foreign('item_id', 'fk_stock_balances_item')->references('id')->on('items')->restrictOnDelete();
            }
        });

        Schema::table('stock_mutations', function (Blueprint $table) {
            $table->dropIndex('idx_stock_mutations_target');
            $table->dropColumn('stock_target');
        });

        Schema::table('items', function (Blueprint $table) {
            $table->dropForeign('fk_items_base_unit');
            $table->dropColumn(['base_unit_id', 'inventory_ratio']);
        });
    }
};
