<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('stock_balances')) {
            return;
        }

        Schema::table('stock_balances', function (Blueprint $table): void {
            if (! Schema::hasColumn('stock_balances', 'avg_cost')) {
                $table->decimal('avg_cost', 20, 4)->default(0)->after('qty_on_hand');
            }

            if (! Schema::hasColumn('stock_balances', 'total_value')) {
                $table->decimal('total_value', 20, 4)->default(0)->after('avg_cost');
            }

            if (! Schema::hasColumn('stock_balances', 'last_mutation_at')) {
                $table->dateTime('last_mutation_at')->nullable()->after('last_mutation_id');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('stock_balances')) {
            return;
        }

        Schema::table('stock_balances', function (Blueprint $table): void {
            foreach (['last_mutation_at', 'total_value', 'avg_cost'] as $column) {
                if (Schema::hasColumn('stock_balances', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
