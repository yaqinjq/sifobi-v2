<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabel "saldo mandiri" Inventory Simple -- IN/OUT harian per item,
     * dipakai untuk fitur Rekap Mingguan (lihat
     * InventoryCountMovementService). SENGAJA TERISOLASI dari
     * stock_balances/stock_mutations (ledger resmi SIFOBI yang dipakai
     * Opname/POS/Purchase Order/dll) -- modul Inventory Simple tetap
     * TIDAK PERNAH membaca/menulis ledger resmi, sesuai batasan yang
     * ditegaskan berulang kali sepanjang proyek ini. Saldo di sini murni
     * kalkulasi internal modul ini, dilabeli jelas di UI sebagai bukan
     * saldo stok sistem.
     */
    public function up(): void
    {
        Schema::create('inventory_count_movements', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('outlet_id');
            $table->unsignedBigInteger('department_id');
            $table->unsignedBigInteger('item_id');
            $table->date('movement_date');
            // Nullable, BUKAN default 0 -- membedakan "belum pernah diisi
            // hari ini" dari "diisi eksplisit 0" (prinsip yang sama dengan
            // InventoryCountLine.is_counted).
            $table->decimal('qty_in', 18, 6)->nullable();
            $table->decimal('qty_out', 18, 6)->nullable();
            $table->unsignedBigInteger('recorded_by')->nullable();
            $table->timestamps();

            $table->unique(
                ['tenant_id', 'outlet_id', 'department_id', 'item_id', 'movement_date'],
                'uq_inv_count_movements_scope_date'
            );
            $table->index(['tenant_id', 'outlet_id', 'department_id', 'item_id'], 'idx_inv_count_movements_item');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_count_movements');
    }
};
