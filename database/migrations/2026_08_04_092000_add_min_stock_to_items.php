<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('items', 'min_stock')) {
            return;
        }

        Schema::table('items', function (Blueprint $table): void {
            $table->decimal('min_stock', 18, 6)->default(0)->after('standard_cost')
                ->comment('Minimum stok; 0 = tidak ada batas minimum');
        });
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table): void {
            if (Schema::hasColumn('items', 'min_stock')) {
                $table->dropColumn('min_stock');
            }
        });
    }
};
