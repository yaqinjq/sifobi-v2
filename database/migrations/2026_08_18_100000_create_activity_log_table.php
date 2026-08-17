<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_log', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained(indexName: 'fk_activity_log_tenant')->cascadeOnDelete();
            $table->string('log_name')->nullable();
            $table->text('description');
            $table->nullableMorphs('subject', 'subject');
            $table->string('event')->nullable();
            $table->nullableMorphs('causer', 'causer');
            $table->json('attribute_changes')->nullable();
            $table->json('properties')->nullable();
            $table->timestamps();

            $table->index('log_name');
            $table->index(['tenant_id', 'log_name'], 'idx_activity_log_tenant_lognm');
            $table->index(['tenant_id', 'created_at'], 'idx_activity_log_tenant_created');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_log');
    }
};
