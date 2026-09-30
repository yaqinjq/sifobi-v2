<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tambah pemisahan Qty Utuh (satuan inventory, mis. DUS) + Qty Ecer (satuan
 * dasar, mis. PCS/GRAM) untuk Spoil & Waste -- meniru pola yang sudah ada di
 * Open Stock (qty_whole/qty_loose) dan Opname (physical_qty_whole/loose).
 * Sebelumnya form Catat Spoil cuma punya 1 field qty generik dalam SATU unit
 * pilihan bebas, bikin bingung (laporan user: field itu kadang menampilkan
 * satuan terkecil/base unit yang notabene tidak selalu butuh desimal, tanpa
 * cara mencatat sisa "ecer" terpisah dari "utuh").
 *
 * Nullable & tidak mengganti kolom qty/unit_id lama -- data historis (input
 * manual lama & hasil import Excel) tetap terbaca apa adanya lewat qty/unit,
 * cuma tidak punya breakdown utuh/ecer (qty_whole/qty_loose tetap NULL untuk
 * baris lama, dibedakan dari nilai 0 yang berarti "memang nol").
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('spoil_wastes', function (Blueprint $table): void {
            if (! Schema::hasColumn('spoil_wastes', 'qty_whole')) {
                $table->decimal('qty_whole', 18, 6)->nullable()->after('qty');
            }
            if (! Schema::hasColumn('spoil_wastes', 'qty_loose')) {
                $table->decimal('qty_loose', 18, 6)->nullable()->after('qty_whole');
            }
        });
    }

    public function down(): void
    {
        Schema::table('spoil_wastes', function (Blueprint $table): void {
            foreach (['qty_whole', 'qty_loose'] as $column) {
                if (Schema::hasColumn('spoil_wastes', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
