<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('goods_receipt_items', 'variance_reason')) {
            return;
        }

        Schema::table('goods_receipt_items', function (Blueprint $table): void {
            $table->string('variance_reason', 30)->nullable()->after('item_status')
                ->comment('Alasan selisih qty_ordered vs qty_received: KURANG_VENDOR, LEBIH_VENDOR, RUSAK, SALAH_ITEM, LAINNYA');
        });
    }

    public function down(): void
    {
        Schema::table('goods_receipt_items', function (Blueprint $table): void {
            if (Schema::hasColumn('goods_receipt_items', 'variance_reason')) {
                $table->dropColumn('variance_reason');
            }
        });
    }
};
