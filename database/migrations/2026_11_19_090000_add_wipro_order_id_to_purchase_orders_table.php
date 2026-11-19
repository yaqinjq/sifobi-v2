<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `external_reference` sudah ada (menyimpan wipro_order_number, mis.
 * "WIP-20261112-0004"), tapi wipro_order_id mentah (ID numerik internal
 * Wipro, mis. 759) sebelumnya tidak pernah disimpan sama sekali --
 * dibuang setelah dipakai sebagai fallback terakhir ke external_reference.
 * Kolom ini dibutuhkan karena dokumen "Detail Order"/surat jalan yang
 * dicetak Wipro sendiri menyertakan QR yang isinya URL
 * "https://wipro.mykopiogroup.com/orders/{wipro_order_id}" -- BUKAN
 * wipro_order_number -- jadi tanpa kolom ini SIFOBI tidak mungkin
 * mencocokkan QR itu balik ke PO yang benar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table): void {
            $table->string('wipro_order_id', 50)->nullable()->after('external_reference');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table): void {
            $table->dropColumn('wipro_order_id');
        });
    }
};
