<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_shifts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained(indexName: 'fk_pos_shifts_tenant')->cascadeOnDelete();
            $table->foreignId('outlet_id')->constrained(indexName: 'fk_pos_shifts_outlet')->cascadeOnDelete();
            $table->string('status', 16)->default('OPEN');
            $table->decimal('opening_cash', 19, 4)->default(0);
            $table->foreignId('opened_by')->nullable()->constrained('users', indexName: 'fk_pos_shifts_opened_by')->nullOnDelete();
            $table->timestamp('opened_at')->nullable();
            $table->decimal('closing_cash_expected', 19, 4)->nullable();
            $table->decimal('closing_cash_actual', 19, 4)->nullable();
            $table->decimal('variance', 19, 4)->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users', indexName: 'fk_pos_shifts_closed_by')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('reconciled_by')->nullable()->constrained('users', indexName: 'fk_pos_shifts_reconciled_by')->nullOnDelete();
            $table->timestamp('reconciled_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'outlet_id', 'status'], 'idx_pos_shifts_tenant_outlet_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_shifts');
    }
};
