<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('members', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained(indexName: 'fk_members_tenant')->cascadeOnDelete();
            $table->string('name', 150);
            $table->string('phone', 20);
            $table->decimal('points_balance', 12, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'phone'], 'uq_members_tenant_phone');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('members');
    }
};
