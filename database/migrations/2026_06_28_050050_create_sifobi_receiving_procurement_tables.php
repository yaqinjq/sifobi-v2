<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('goods_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained(indexName: 'fk_goods_receipts_tenant')->cascadeOnDelete();
            $table->foreignId('outlet_id')->constrained(indexName: 'fk_goods_receipts_outlet')->restrictOnDelete();
            $table->string('receipt_number', 64)->nullable();
            $table->string('source_type', 48);
            $table->string('source_reference', 128)->nullable();
            $table->string('vendor_name')->nullable();
            $table->timestamp('received_at');
            $table->string('status', 32)->default('DRAFT')->index('idx_goods_receipts_status');
            $table->foreignId('received_by')->nullable()->constrained(table: 'users', indexName: 'fk_goods_receipts_received_by')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained(table: 'users', indexName: 'fk_goods_receipts_approved_by')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'receipt_number'], 'uq_goods_receipts_number');
            $table->index(['tenant_id', 'outlet_id', 'received_at'], 'idx_goods_receipts_received');
            $table->index(['tenant_id', 'source_type'], 'idx_goods_receipts_source');
        });

        Schema::create('goods_receipt_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained(indexName: 'fk_gr_items_tenant')->cascadeOnDelete();
            $table->foreignId('goods_receipt_id')->constrained(indexName: 'fk_gr_items_receipt')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained(indexName: 'fk_gr_items_item')->restrictOnDelete();
            $table->foreignId('unit_id')->constrained(table: 'units', indexName: 'fk_gr_items_unit')->restrictOnDelete();
            $table->decimal('qty_received', 18, 6);
            $table->decimal('unit_cost', 19, 4)->nullable();
            $table->foreignId('mutation_id')->nullable()->constrained(table: 'stock_mutations', indexName: 'fk_gr_items_mutation')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'goods_receipt_id'], 'idx_gr_items_receipt');
        });

        Schema::create('document_captures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained(indexName: 'fk_doc_captures_tenant')->cascadeOnDelete();
            $table->foreignId('outlet_id')->nullable()->constrained(indexName: 'fk_doc_captures_outlet')->nullOnDelete();
            $table->nullableMorphs('capturable', 'idx_doc_captures_capturable');
            $table->string('capture_type', 48)->default('PHOTO');
            $table->string('source_type', 48)->nullable();
            $table->string('file_path');
            $table->string('file_hash', 128)->nullable();
            $table->string('status', 32)->default('CAPTURED')->index('idx_doc_captures_status');
            $table->foreignId('captured_by')->nullable()->constrained(table: 'users', indexName: 'fk_doc_captures_captured_by')->nullOnDelete();
            $table->timestamp('captured_at');
            $table->text('device_info')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'source_type'], 'idx_doc_captures_source');
        });

        Schema::create('document_capture_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained(indexName: 'fk_doc_capture_items_tenant')->cascadeOnDelete();
            $table->foreignId('document_capture_id')->constrained(indexName: 'fk_doc_capture_items_capture')->cascadeOnDelete();
            $table->foreignId('item_id')->nullable()->constrained(indexName: 'fk_doc_capture_items_item')->nullOnDelete();
            $table->string('raw_name')->nullable();
            $table->foreignId('unit_id')->nullable()->constrained(table: 'units', indexName: 'fk_doc_capture_items_unit')->nullOnDelete();
            $table->decimal('qty', 18, 6)->nullable();
            $table->decimal('unit_cost', 19, 4)->nullable();
            $table->decimal('confidence', 5, 4)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained(indexName: 'fk_purchase_orders_tenant')->cascadeOnDelete();
            $table->foreignId('outlet_id')->constrained(indexName: 'fk_purchase_orders_outlet')->restrictOnDelete();
            $table->foreignId('department_id')->nullable()->constrained(indexName: 'fk_purchase_orders_department')->nullOnDelete();
            $table->string('po_number', 64);
            $table->string('po_type', 48)->default('OUTLET_REQUEST');
            $table->date('needed_at')->nullable();
            $table->string('status', 32)->default('DRAFT')->index('idx_purchase_orders_status');
            $table->foreignId('requested_by')->nullable()->constrained(table: 'users', indexName: 'fk_purchase_orders_requested_by')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained(table: 'users', indexName: 'fk_purchase_orders_approved_by')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'po_number'], 'uq_purchase_orders_number');
            $table->index(['tenant_id', 'outlet_id', 'status'], 'idx_purchase_orders_scope');
        });

        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained(indexName: 'fk_po_items_tenant')->cascadeOnDelete();
            $table->foreignId('purchase_order_id')->constrained(indexName: 'fk_po_items_order')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained(indexName: 'fk_po_items_item')->restrictOnDelete();
            $table->foreignId('unit_id')->constrained(table: 'units', indexName: 'fk_po_items_unit')->restrictOnDelete();
            $table->decimal('qty_ordered', 18, 6);
            $table->decimal('unit_cost', 19, 4)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'purchase_order_id'], 'idx_po_items_order');
        });

        Schema::create('purchase_order_approval_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained(indexName: 'fk_po_approval_events_tenant')->cascadeOnDelete();
            $table->foreignId('purchase_order_id')->constrained(indexName: 'fk_po_approval_events_order')->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained(table: 'users', indexName: 'fk_po_approval_events_actor')->nullOnDelete();
            $table->string('event_type', 48);
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'purchase_order_id'], 'idx_po_approval_events_order');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_order_approval_events');
        Schema::dropIfExists('purchase_order_items');
        Schema::dropIfExists('purchase_orders');
        Schema::dropIfExists('document_capture_items');
        Schema::dropIfExists('document_captures');
        Schema::dropIfExists('goods_receipt_items');
        Schema::dropIfExists('goods_receipts');
    }
};
