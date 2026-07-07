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
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique('uq_tenants_code');
            $table->string('name');
            $table->string('status', 24)->default('ACTIVE')->index('idx_tenants_status');
            $table->json('settings')->nullable();
            $table->timestamps();
        });

        Schema::create('groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained(indexName: 'fk_groups_tenant')->cascadeOnDelete();
            $table->string('code', 32);
            $table->string('name');
            $table->string('status', 24)->default('ACTIVE')->index('idx_groups_status');
            $table->timestamps();

            $table->unique(['tenant_id', 'code'], 'uq_groups_tenant_code');
        });

        Schema::create('legal_entities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained(indexName: 'fk_legal_entities_tenant')->cascadeOnDelete();
            $table->foreignId('group_id')->nullable()->constrained(table: 'groups', indexName: 'fk_legal_entities_group')->nullOnDelete();
            $table->string('code', 32);
            $table->string('name');
            $table->string('tax_number', 64)->nullable();
            $table->string('status', 24)->default('ACTIVE')->index('idx_legal_entities_status');
            $table->timestamps();

            $table->unique(['tenant_id', 'code'], 'uq_legal_entities_tenant_code');
        });

        Schema::create('brands', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained(indexName: 'fk_brands_tenant')->cascadeOnDelete();
            $table->foreignId('group_id')->nullable()->constrained(table: 'groups', indexName: 'fk_brands_group')->nullOnDelete();
            $table->string('code', 32);
            $table->string('name');
            $table->string('status', 24)->default('ACTIVE')->index('idx_brands_status');
            $table->timestamps();

            $table->unique(['tenant_id', 'code'], 'uq_brands_tenant_code');
        });

        Schema::create('outlets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained(indexName: 'fk_outlets_tenant')->cascadeOnDelete();
            $table->foreignId('brand_id')->constrained(indexName: 'fk_outlets_brand')->restrictOnDelete();
            $table->foreignId('legal_entity_id')->constrained(indexName: 'fk_outlets_legal_entity')->restrictOnDelete();
            $table->string('code', 32);
            $table->string('name');
            $table->string('outlet_type', 32)->default('OUTLET');
            $table->string('timezone', 64)->default('Asia/Jakarta');
            $table->text('address')->nullable();
            $table->string('status', 24)->default('ACTIVE')->index('idx_outlets_status');
            $table->timestamps();

            $table->unique(['tenant_id', 'code'], 'uq_outlets_tenant_code');
            $table->index(['tenant_id', 'brand_id'], 'idx_outlets_tenant_brand');
            $table->index(['tenant_id', 'legal_entity_id'], 'idx_outlets_tenant_legal_entity');
        });

        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained(indexName: 'fk_departments_tenant')->cascadeOnDelete();
            $table->string('code', 32);
            $table->string('name');
            $table->boolean('is_operational')->default(true);
            $table->string('status', 24)->default('ACTIVE')->index('idx_departments_status');
            $table->timestamps();

            $table->unique(['tenant_id', 'code'], 'uq_departments_tenant_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('departments');
        Schema::dropIfExists('outlets');
        Schema::dropIfExists('brands');
        Schema::dropIfExists('legal_entities');
        Schema::dropIfExists('groups');
        Schema::dropIfExists('tenants');
    }
};
