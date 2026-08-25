<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menu_categories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained(indexName: 'fk_menu_categories_tenant')->cascadeOnDelete();
            $table->string('code', 50);
            $table->string('name', 150);
            $table->string('color', 20)->default('gray');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['tenant_id', 'code'], 'uq_menu_categories_tenant_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_categories');
    }
};
