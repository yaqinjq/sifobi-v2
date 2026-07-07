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
        Schema::create('open_stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained(indexName: 'fk_open_stocks_tenant')->cascadeOnDelete();
            $table->foreignId('outlet_id')->constrained(indexName: 'fk_open_stocks_outlet')->restrictOnDelete();
            $table->foreignId('department_id')->nullable()->constrained(indexName: 'fk_open_stocks_department')->nullOnDelete();
            $table->date('business_date');
            $table->string('status', 32)->default('DRAFT')->index('idx_open_stocks_status');
            $table->foreignId('posted_by')->nullable()->constrained(table: 'users', indexName: 'fk_open_stocks_posted_by')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'outlet_id', 'department_id', 'business_date'], 'uq_open_stocks_scope');
        });

        Schema::create('opname_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained(indexName: 'fk_opname_sessions_tenant')->cascadeOnDelete();
            $table->foreignId('outlet_id')->constrained(indexName: 'fk_opname_sessions_outlet')->restrictOnDelete();
            $table->foreignId('department_id')->nullable()->constrained(indexName: 'fk_opname_sessions_department')->nullOnDelete();
            $table->string('opname_type', 24)->default('DAILY');
            $table->date('business_date');
            $table->string('status', 32)->default('DRAFT')->index('idx_opname_sessions_status');
            $table->foreignId('started_by')->nullable()->constrained(table: 'users', indexName: 'fk_opname_sessions_started_by')->nullOnDelete();
            $table->foreignId('posted_by')->nullable()->constrained(table: 'users', indexName: 'fk_opname_sessions_posted_by')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'outlet_id', 'business_date'], 'idx_opname_sessions_scope');
        });

        Schema::create('opname_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained(indexName: 'fk_opname_items_tenant')->cascadeOnDelete();
            $table->foreignId('opname_session_id')->constrained(indexName: 'fk_opname_items_session')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained(indexName: 'fk_opname_items_item')->restrictOnDelete();
            $table->foreignId('unit_id')->constrained(table: 'units', indexName: 'fk_opname_items_unit')->restrictOnDelete();
            $table->decimal('system_qty', 18, 6)->default(0);
            $table->decimal('counted_qty', 18, 6)->default(0);
            $table->decimal('variance_qty', 18, 6)->default(0);
            $table->foreignId('mutation_id')->nullable()->constrained(table: 'stock_mutations', indexName: 'fk_opname_items_mutation')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'opname_session_id', 'item_id'], 'uq_opname_items_scope');
        });

        Schema::create('spoil_wastes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained(indexName: 'fk_spoil_wastes_tenant')->cascadeOnDelete();
            $table->foreignId('outlet_id')->constrained(indexName: 'fk_spoil_wastes_outlet')->restrictOnDelete();
            $table->foreignId('department_id')->nullable()->constrained(indexName: 'fk_spoil_wastes_department')->nullOnDelete();
            $table->foreignId('item_id')->constrained(indexName: 'fk_spoil_wastes_item')->restrictOnDelete();
            $table->foreignId('unit_id')->constrained(table: 'units', indexName: 'fk_spoil_wastes_unit')->restrictOnDelete();
            $table->decimal('qty', 18, 6);
            $table->timestamp('recorded_at');
            $table->string('photo_path')->nullable();
            $table->string('photo_hash', 128)->nullable();
            $table->string('perceptual_hash', 128)->nullable();
            $table->text('device_info')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('approval_status', 32)->default('PENDING')->index('idx_spoil_wastes_approval');
            $table->foreignId('approved_by')->nullable()->constrained(table: 'users', indexName: 'fk_spoil_wastes_approved_by')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('mutation_id')->nullable()->constrained(table: 'stock_mutations', indexName: 'fk_spoil_wastes_mutation')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'outlet_id', 'recorded_at'], 'idx_spoil_wastes_scope');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('spoil_wastes');
        Schema::dropIfExists('opname_items');
        Schema::dropIfExists('opname_sessions');
        Schema::dropIfExists('open_stocks');
    }
};
