<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pos_payments', function (Blueprint $table): void {
            $table->foreignId('pos_shift_id')->nullable()->after('pos_order_id')
                ->constrained(indexName: 'fk_pos_payments_shift')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('pos_payments', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('pos_shift_id');
        });
    }
};
