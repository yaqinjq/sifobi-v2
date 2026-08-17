<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menus', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained(indexName: 'fk_menus_tenant')->cascadeOnDelete();
            $table->foreignId('brand_id')->constrained(indexName: 'fk_menus_brand')->restrictOnDelete();
            $table->string('code', 32)->nullable();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'brand_id'], 'idx_menus_tenant_brand');
        });

        Schema::create('recipes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained(indexName: 'fk_recipes_tenant')->cascadeOnDelete();
            $table->foreignId('menu_id')->constrained(indexName: 'fk_recipes_menu')->cascadeOnDelete();
            $table->unsignedInteger('version_number');
            $table->string('status', 24)->default('DRAFT');
            $table->foreignId('created_by')->nullable()->constrained(table: 'users', indexName: 'fk_recipes_created_by')->nullOnDelete();
            $table->date('test_date')->nullable();
            $table->json('witnessed_by_user_ids')->nullable();
            $table->text('witnessed_by_names')->nullable();
            $table->json('food_panel_user_ids')->nullable();
            $table->text('food_panel_names')->nullable();
            $table->decimal('volume_production', 12, 4)->default(1);
            $table->text('notes')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained(table: 'users', indexName: 'fk_recipes_approved_by')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('rejected_reason')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'menu_id'], 'idx_recipes_tenant_menu');
            $table->index(['tenant_id', 'status'], 'idx_recipes_tenant_status');
            $table->unique(['menu_id', 'version_number'], 'uq_recipes_menu_version');
        });

        Schema::create('recipe_ingredients', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained(indexName: 'fk_recipe_ingredients_tenant')->cascadeOnDelete();
            $table->foreignId('recipe_id')->constrained(indexName: 'fk_recipe_ingredients_recipe')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained(indexName: 'fk_recipe_ingredients_item')->restrictOnDelete();
            $table->decimal('buy_qty', 18, 6)->default(0);
            $table->foreignId('buy_unit_id')->constrained(table: 'units', indexName: 'fk_recipe_ingredients_buy_unit')->restrictOnDelete();
            $table->decimal('buy_price', 19, 4)->default(0)->comment('Harga beli manual, bukan dari stock_balances.avg_cost');
            $table->decimal('recipe_qty', 18, 6)->default(0);
            $table->foreignId('recipe_unit_id')->constrained(table: 'units', indexName: 'fk_recipe_ingredients_recipe_unit')->restrictOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['tenant_id', 'recipe_id'], 'idx_recipe_ingredients_recipe');
        });

        Schema::create('recipe_other_costs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained(indexName: 'fk_recipe_other_costs_tenant')->cascadeOnDelete();
            $table->foreignId('recipe_id')->constrained(indexName: 'fk_recipe_other_costs_recipe')->cascadeOnDelete();
            $table->string('cost_type', 24)->comment('PRODUCTION atau OVERHEAD');
            $table->string('label');
            $table->decimal('amount', 19, 4)->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['tenant_id', 'recipe_id'], 'idx_recipe_other_costs_recipe');
        });

        Schema::create('recipe_outlets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained(indexName: 'fk_recipe_outlets_tenant')->cascadeOnDelete();
            $table->foreignId('recipe_id')->constrained(indexName: 'fk_recipe_outlets_recipe')->cascadeOnDelete();
            $table->foreignId('outlet_id')->constrained(indexName: 'fk_recipe_outlets_outlet')->cascadeOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained(table: 'users', indexName: 'fk_recipe_outlets_assigned_by')->nullOnDelete();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'outlet_id'], 'idx_recipe_outlets_outlet');
            $table->index(['tenant_id', 'recipe_id'], 'idx_recipe_outlets_recipe');
        });

        Schema::create('recipe_approval_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained(indexName: 'fk_recipe_approval_events_tenant')->cascadeOnDelete();
            $table->foreignId('recipe_id')->constrained(indexName: 'fk_recipe_approval_events_recipe')->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained(table: 'users', indexName: 'fk_recipe_approval_events_actor')->nullOnDelete();
            $table->string('event_type', 48);
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'recipe_id'], 'idx_recipe_approval_events_recipe');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recipe_approval_events');
        Schema::dropIfExists('recipe_outlets');
        Schema::dropIfExists('recipe_other_costs');
        Schema::dropIfExists('recipe_ingredients');
        Schema::dropIfExists('recipes');
        Schema::dropIfExists('menus');
    }
};
