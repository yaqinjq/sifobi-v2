<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('suppliers')) {
            Schema::create('suppliers', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('tenant_id');
                $table->string('code', 50);
                $table->string('name');
                $table->string('contact_name', 150)->nullable();
                $table->string('phone', 50)->nullable();
                $table->string('email', 150)->nullable();
                $table->text('address')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->unique(['tenant_id', 'code'], 'uq_suppliers_tenant_code');
                $table->index('tenant_id', 'idx_suppliers_tenant');
                $table->foreign('tenant_id', 'fk_suppliers_tenant')
                    ->references('id')->on('tenants')->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('goods_receipts')) {
            Schema::create('goods_receipts', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('tenant_id');
                $table->string('code', 50);
                $table->unsignedBigInteger('outlet_id');
                $table->string('source', 48);
                $table->string('external_po_number', 120)->nullable();
                $table->unsignedBigInteger('supplier_id')->nullable();
                $table->string('supplier_name', 150)->nullable();
                $table->string('doc_number', 120)->nullable();
                $table->string('invoice_number', 120)->nullable();
                $table->string('photo_document')->nullable();
                $table->date('receipt_date');
                $table->dateTime('received_at')->nullable();
                $table->string('status', 32)->default('DRAFT');
                $table->string('review_status', 32)->default('NONE');
                $table->unsignedBigInteger('created_by');
                $table->unsignedBigInteger('submitted_by')->nullable();
                $table->unsignedBigInteger('reviewed_by')->nullable();
                $table->dateTime('reviewed_at')->nullable();
                $table->text('review_notes')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->unique('code', 'uq_goods_receipts_code');
                $table->index(['tenant_id', 'outlet_id', 'status'], 'idx_gr_tenant_outlet_status');
                $table->index(['tenant_id', 'source'], 'idx_gr_tenant_source');
                $table->index(['tenant_id', 'receipt_date'], 'idx_gr_tenant_date');
                $table->foreign('tenant_id', 'fk_gr_tenant')->references('id')->on('tenants')->cascadeOnDelete();
                $table->foreign('outlet_id', 'fk_gr_outlet')->references('id')->on('outlets')->restrictOnDelete();
                $table->foreign('supplier_id', 'fk_gr_supplier')->references('id')->on('suppliers')->nullOnDelete();
                $table->foreign('created_by', 'fk_gr_created_by')->references('id')->on('users')->restrictOnDelete();
                $table->foreign('submitted_by', 'fk_gr_submitted_by')->references('id')->on('users')->nullOnDelete();
                $table->foreign('reviewed_by', 'fk_gr_reviewed_by')->references('id')->on('users')->nullOnDelete();
            });
        } else {
            $this->upgradeGoodsReceipts();
        }

        if (! Schema::hasTable('goods_receipt_items')) {
            Schema::create('goods_receipt_items', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('tenant_id');
                $table->unsignedBigInteger('goods_receipt_id');
                $table->unsignedBigInteger('item_id');
                $table->unsignedBigInteger('unit_id');
                $table->decimal('qty_ordered', 18, 6)->default(0);
                $table->decimal('qty_received', 18, 6)->default(0);
                $table->decimal('qty_in_base_unit', 18, 6)->default(0);
                $table->decimal('qty_short', 18, 6)->default(0);
                $table->decimal('qty_over', 18, 6)->default(0);
                $table->decimal('unit_price', 19, 4)->default(0);
                $table->decimal('unit_cost', 19, 4)->nullable();
                $table->decimal('total_value', 19, 4)->default(0);
                $table->string('item_status', 32)->default('OK');
                $table->date('expired_date')->nullable();
                $table->string('batch_code', 100)->nullable();
                $table->unsignedBigInteger('mutation_id')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['tenant_id', 'goods_receipt_id'], 'idx_gr_items_receipt');
                $table->index('item_id', 'idx_gr_items_item');
                $table->foreign('tenant_id', 'fk_gr_items_tenant')->references('id')->on('tenants')->cascadeOnDelete();
                $table->foreign('goods_receipt_id', 'fk_gr_items_receipt')->references('id')->on('goods_receipts')->cascadeOnDelete();
                $table->foreign('item_id', 'fk_gr_items_item')->references('id')->on('items')->restrictOnDelete();
                $table->foreign('unit_id', 'fk_gr_items_unit')->references('id')->on('units')->restrictOnDelete();
                $table->foreign('mutation_id', 'fk_gr_items_mutation')->references('id')->on('stock_mutations')->nullOnDelete();
            });
        } else {
            $this->upgradeGoodsReceiptItems();
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('goods_receipt_items')) {
            Schema::table('goods_receipt_items', function (Blueprint $table): void {
                foreach (['qty_ordered', 'qty_in_base_unit', 'qty_short', 'qty_over', 'unit_price', 'total_value', 'item_status', 'expired_date', 'batch_code'] as $column) {
                    if (Schema::hasColumn('goods_receipt_items', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('goods_receipts')) {
            Schema::table('goods_receipts', function (Blueprint $table): void {
                foreach (['code', 'source', 'external_po_number', 'supplier_id', 'supplier_name', 'doc_number', 'invoice_number', 'photo_document', 'receipt_date', 'review_status', 'created_by', 'submitted_by', 'reviewed_by', 'reviewed_at', 'review_notes', 'deleted_at'] as $column) {
                    if (Schema::hasColumn('goods_receipts', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        Schema::dropIfExists('suppliers');
    }

    private function upgradeGoodsReceipts(): void
    {
        $columns = [
            'code' => fn (Blueprint $table) => $table->string('code', 50)->nullable(),
            'source' => fn (Blueprint $table) => $table->string('source', 48)->nullable(),
            'external_po_number' => fn (Blueprint $table) => $table->string('external_po_number', 120)->nullable(),
            'supplier_id' => fn (Blueprint $table) => $table->unsignedBigInteger('supplier_id')->nullable(),
            'supplier_name' => fn (Blueprint $table) => $table->string('supplier_name', 150)->nullable(),
            'doc_number' => fn (Blueprint $table) => $table->string('doc_number', 120)->nullable(),
            'invoice_number' => fn (Blueprint $table) => $table->string('invoice_number', 120)->nullable(),
            'photo_document' => fn (Blueprint $table) => $table->string('photo_document')->nullable(),
            'receipt_date' => fn (Blueprint $table) => $table->date('receipt_date')->nullable(),
            'review_status' => fn (Blueprint $table) => $table->string('review_status', 32)->default('NONE'),
            'created_by' => fn (Blueprint $table) => $table->unsignedBigInteger('created_by')->nullable(),
            'submitted_by' => fn (Blueprint $table) => $table->unsignedBigInteger('submitted_by')->nullable(),
            'reviewed_by' => fn (Blueprint $table) => $table->unsignedBigInteger('reviewed_by')->nullable(),
            'reviewed_at' => fn (Blueprint $table) => $table->dateTime('reviewed_at')->nullable(),
            'review_notes' => fn (Blueprint $table) => $table->text('review_notes')->nullable(),
            'deleted_at' => fn (Blueprint $table) => $table->softDeletes(),
        ];

        foreach ($columns as $column => $definition) {
            if (! Schema::hasColumn('goods_receipts', $column)) {
                Schema::table('goods_receipts', fn (Blueprint $table) => $definition($table));
            }
        }

        Schema::table('goods_receipts', function (Blueprint $table): void {
            $table->unique('code', 'uq_goods_receipts_code');
            $table->index(['tenant_id', 'outlet_id', 'status'], 'idx_gr_tenant_outlet_status');
            $table->index(['tenant_id', 'source'], 'idx_gr_tenant_source');
            $table->index(['tenant_id', 'receipt_date'], 'idx_gr_tenant_date');
        });

        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        Schema::table('goods_receipts', function (Blueprint $table): void {
            $table->foreign('supplier_id', 'fk_gr_supplier')->references('id')->on('suppliers')->nullOnDelete();
            $table->foreign('submitted_by', 'fk_gr_submitted_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('reviewed_by', 'fk_gr_reviewed_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    private function upgradeGoodsReceiptItems(): void
    {
        $columns = [
            'qty_ordered' => fn (Blueprint $table) => $table->decimal('qty_ordered', 18, 6)->default(0),
            'qty_in_base_unit' => fn (Blueprint $table) => $table->decimal('qty_in_base_unit', 18, 6)->default(0),
            'qty_short' => fn (Blueprint $table) => $table->decimal('qty_short', 18, 6)->default(0),
            'qty_over' => fn (Blueprint $table) => $table->decimal('qty_over', 18, 6)->default(0),
            'unit_price' => fn (Blueprint $table) => $table->decimal('unit_price', 19, 4)->default(0),
            'total_value' => fn (Blueprint $table) => $table->decimal('total_value', 19, 4)->default(0),
            'item_status' => fn (Blueprint $table) => $table->string('item_status', 32)->default('OK'),
            'expired_date' => fn (Blueprint $table) => $table->date('expired_date')->nullable(),
            'batch_code' => fn (Blueprint $table) => $table->string('batch_code', 100)->nullable(),
        ];

        foreach ($columns as $column => $definition) {
            if (! Schema::hasColumn('goods_receipt_items', $column)) {
                Schema::table('goods_receipt_items', fn (Blueprint $table) => $definition($table));
            }
        }

        Schema::table('goods_receipt_items', function (Blueprint $table): void {
            $table->index('item_id', 'idx_gr_items_item');
        });
    }
};
