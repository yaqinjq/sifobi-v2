<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_areas', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained(indexName: 'fk_pos_areas_tenant')->cascadeOnDelete();
            $table->foreignId('outlet_id')->constrained(indexName: 'fk_pos_areas_outlet')->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['tenant_id', 'outlet_id'], 'idx_pos_areas_tenant_outlet');
        });

        Schema::create('pos_tables', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained(indexName: 'fk_pos_tables_tenant')->cascadeOnDelete();
            $table->foreignId('outlet_id')->constrained(indexName: 'fk_pos_tables_outlet')->cascadeOnDelete();
            $table->foreignId('pos_area_id')->constrained(indexName: 'fk_pos_tables_area')->cascadeOnDelete();
            $table->string('code', 32);
            $table->unsignedInteger('capacity')->nullable();
            $table->integer('pos_x')->default(0);
            $table->integer('pos_y')->default(0);
            $table->string('shape', 16)->default('SQUARE');
            $table->string('status', 16)->default('AVAILABLE');
            $table->timestamps();

            $table->unique(['outlet_id', 'code'], 'uq_pos_tables_outlet_code');
            $table->index(['tenant_id', 'outlet_id'], 'idx_pos_tables_tenant_outlet');
        });

        Schema::create('pos_orders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained(indexName: 'fk_pos_orders_tenant')->cascadeOnDelete();
            $table->foreignId('outlet_id')->constrained(indexName: 'fk_pos_orders_outlet')->cascadeOnDelete();
            $table->foreignId('pos_table_id')->nullable()->constrained(indexName: 'fk_pos_orders_table')->nullOnDelete();
            $table->string('order_number', 40);
            $table->string('order_type', 16);
            $table->string('status', 16)->default('OPEN');
            $table->decimal('subtotal', 19, 4)->default(0);
            $table->decimal('discount_amount', 19, 4)->default(0);
            $table->decimal('tax_amount', 19, 4)->default(0);
            $table->decimal('service_charge_amount', 19, 4)->default(0);
            $table->decimal('total_amount', 19, 4)->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users', indexName: 'fk_pos_orders_created_by')->nullOnDelete();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'order_number'], 'uq_pos_orders_tenant_order_number');
            $table->index(['tenant_id', 'outlet_id', 'status'], 'idx_pos_orders_tenant_outlet_status');
        });

        Schema::create('pos_order_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained(indexName: 'fk_pos_order_items_tenant')->cascadeOnDelete();
            $table->foreignId('pos_order_id')->constrained(indexName: 'fk_pos_order_items_order')->cascadeOnDelete();
            $table->foreignId('menu_id')->constrained(indexName: 'fk_pos_order_items_menu')->restrictOnDelete();
            $table->string('item_name');
            $table->decimal('unit_price', 19, 4);
            $table->decimal('qty', 12, 4);
            $table->decimal('subtotal', 19, 4);
            $table->string('notes')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['tenant_id', 'pos_order_id'], 'idx_pos_order_items_tenant_order');
        });

        Schema::create('pos_payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained(indexName: 'fk_pos_payments_tenant')->cascadeOnDelete();
            $table->foreignId('pos_order_id')->constrained(indexName: 'fk_pos_payments_order')->cascadeOnDelete();
            $table->string('method', 16);
            $table->decimal('amount', 19, 4);
            $table->string('reference_no')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users', indexName: 'fk_pos_payments_created_by')->nullOnDelete();
            $table->timestamp('paid_at');
            $table->timestamps();

            $table->index(['tenant_id', 'pos_order_id'], 'idx_pos_payments_tenant_order');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_payments');
        Schema::dropIfExists('pos_order_items');
        Schema::dropIfExists('pos_orders');
        Schema::dropIfExists('pos_tables');
        Schema::dropIfExists('pos_areas');
    }
};
