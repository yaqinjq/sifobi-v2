<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('member_point_transactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained(indexName: 'fk_member_point_tx_tenant')->cascadeOnDelete();
            $table->foreignId('member_id')->constrained(indexName: 'fk_member_point_tx_member')->cascadeOnDelete();
            $table->foreignId('pos_order_id')->nullable()->constrained(indexName: 'fk_member_point_tx_order')->nullOnDelete();
            $table->string('type', 16);
            $table->decimal('points', 12, 2);
            $table->decimal('balance_after', 12, 2);
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'member_id'], 'idx_member_point_tx_tenant_member');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_point_transactions');
    }
};
