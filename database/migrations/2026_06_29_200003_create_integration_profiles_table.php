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
        if (Schema::hasTable('integration_profiles')) {
            return;
        }

        Schema::create('integration_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained(indexName: 'fk_integrations_tenant')->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('provider', 50);
            $table->string('base_url', 255);
            $table->string('auth_type', 30)->default('NONE');
            $table->string('api_token')->nullable();
            $table->string('username')->nullable();
            $table->string('password')->nullable();
            $table->string('outlet_sync_path')->default('/api/outlets');
            $table->json('meta')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'provider', 'name'], 'uq_integrations_profile');
            $table->index(['tenant_id', 'provider'], 'idx_integrations_provider');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('integration_profiles');
    }
};
