<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('goods_receipts', function (Blueprint $table): void {
            $table->unsignedBigInteger('purchase_order_shipment_id')
                ->nullable()
                ->after('purchase_order_id');

            $table->foreign('purchase_order_shipment_id', 'fk_gr_po_shipment')
                ->references('id')->on('purchase_order_shipments')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('goods_receipts', function (Blueprint $table): void {
            $table->dropForeign('fk_gr_po_shipment');
            $table->dropColumn('purchase_order_shipment_id');
        });
    }
};
