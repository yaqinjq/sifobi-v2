<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Saldo awal minggu (per item per scope), dipakai sebagai titik mulai
     * perhitungan saldo berjalan di Rekap Mingguan -- lihat catatan
     * isolasi ledger di migration inventory_count_movements. Kalau tidak
     * diisi untuk suatu minggu, service MENELUSURI minggu sebelumnya
     * (bukan default ke 0) -- lihat
     * InventoryCountMovementService::calculateBalance().
     */
    public function up(): void
    {
        Schema::create('inventory_count_opening_balances', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('outlet_id');
            $table->unsignedBigInteger('department_id');
            $table->unsignedBigInteger('item_id');
            $table->date('week_start_date'); // selalu hari Senin.
            $table->decimal('qty', 18, 6);
            $table->unsignedBigInteger('recorded_by')->nullable();
            $table->timestamps();

            $table->unique(
                ['tenant_id', 'outlet_id', 'department_id', 'item_id', 'week_start_date'],
                'uq_inv_count_opening_balances_scope_week'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_count_opening_balances');
    }
};
