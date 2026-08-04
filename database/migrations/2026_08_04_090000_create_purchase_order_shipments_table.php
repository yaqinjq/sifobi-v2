<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_order_shipments', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->foreignId('purchase_order_id')
                ->constrained('purchase_orders')
                ->cascadeOnDelete();
            $table->string('do_number', 120)->nullable();
            $table->string('invoice_number', 120)->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_confirmed')->default(false);
            $table->timestamp('confirmed_at')->nullable();
            $table->unsignedBigInteger('goods_receipt_id')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'purchase_order_id'], 'idx_po_shipments_po');
            $table->index('goods_receipt_id', 'idx_po_shipments_gr');
            $table->foreign('tenant_id', 'fk_po_shipments_tenant')
                ->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('goods_receipt_id', 'fk_po_shipments_gr')
                ->references('id')->on('goods_receipts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_shipments');
    }
};
