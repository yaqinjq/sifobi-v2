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
        Schema::create('stock_mutations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained(indexName: 'fk_stock_mutations_tenant')->cascadeOnDelete();
            $table->foreignId('outlet_id')->constrained(indexName: 'fk_stock_mutations_outlet')->restrictOnDelete();
            $table->foreignId('item_id')->constrained(indexName: 'fk_stock_mutations_item')->restrictOnDelete();
            $table->foreignId('unit_id')->constrained(table: 'units', indexName: 'fk_stock_mutations_unit')->restrictOnDelete();
            $table->foreignId('source_mutation_id')->nullable()->constrained(table: 'stock_mutations', indexName: 'fk_stock_mutations_source')->restrictOnDelete();
            $table->string('mutation_type', 40);
            $table->decimal('qty_change', 18, 6);
            $table->decimal('balance_after', 18, 6)->nullable();
            $table->string('reference_type', 100)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->foreignId('performed_by')->nullable()->constrained(table: 'users', indexName: 'fk_stock_mutations_user')->nullOnDelete();
            $table->timestamp('performed_at')->useCurrent();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'outlet_id', 'item_id'], 'idx_stock_mutations_scope');
            $table->index(['tenant_id', 'mutation_type'], 'idx_stock_mutations_type');
            $table->index(['reference_type', 'reference_id'], 'idx_stock_mutations_reference');
            $table->index('performed_at', 'idx_stock_mutations_performed');
        });

        Schema::create('stock_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained(indexName: 'fk_stock_balances_tenant')->cascadeOnDelete();
            $table->foreignId('outlet_id')->constrained(indexName: 'fk_stock_balances_outlet')->restrictOnDelete();
            $table->foreignId('item_id')->constrained(indexName: 'fk_stock_balances_item')->restrictOnDelete();
            $table->decimal('qty_on_hand', 18, 6)->default(0);
            $table->foreignId('last_mutation_id')->nullable()->constrained(table: 'stock_mutations', indexName: 'fk_stock_balances_last_mutation')->nullOnDelete();
            $table->timestamps();

            $table->unique(['tenant_id', 'outlet_id', 'item_id'], 'uq_stock_balances_scope');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_balances');
        Schema::dropIfExists('stock_mutations');
    }
};
