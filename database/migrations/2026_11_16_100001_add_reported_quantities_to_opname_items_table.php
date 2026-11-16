<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('opname_items', function (Blueprint $table): void {
            // Angka IN/OUT/SPOIL yang dilaporkan outlet lewat form kertas --
            // murni arsip/informasi, TIDAK pernah membentuk mutasi stok.
            // BALANCE (physical_qty_whole/loose yang sudah ada) tetap
            // satu-satunya angka yang menyentuh ledger. nullable TANPA
            // default 0 -- null = tidak dilaporkan, 0 = dilaporkan memang nol.
            $table->decimal('reported_in_qty', 18, 6)->nullable()->after('physical_qty_base');
            $table->decimal('reported_out_qty', 18, 6)->nullable()->after('reported_in_qty');
            $table->decimal('reported_spoil_qty', 18, 6)->nullable()->after('reported_out_qty');
        });
    }

    public function down(): void
    {
        Schema::table('opname_items', function (Blueprint $table): void {
            $table->dropColumn(['reported_in_qty', 'reported_out_qty', 'reported_spoil_qty']);
        });
    }
};
