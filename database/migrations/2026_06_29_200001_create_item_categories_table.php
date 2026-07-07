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
        if (! Schema::hasTable('item_categories')) {
            Schema::create('item_categories', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('tenant_id')->constrained(indexName: 'fk_item_categories_tenant')->cascadeOnDelete();
                $table->foreignId('parent_id')->nullable()->constrained(table: 'item_categories', indexName: 'fk_item_categories_parent')->nullOnDelete();
                $table->string('code', 50);
                $table->string('name', 150);
                $table->text('description')->nullable();
                $table->string('status', 24)->default('ACTIVE');
                $table->boolean('is_active')->default(true);
                $table->integer('sort_order')->default(0);
                $table->timestamps();

                $table->unique(['tenant_id', 'code'], 'uq_item_categories_tenant_code');
                $table->index(['tenant_id', 'is_active'], 'idx_item_categories_active');
            });

            return;
        }

        if (! Schema::hasColumn('item_categories', 'description')) {
            Schema::table('item_categories', function (Blueprint $table): void {
                $table->text('description')->nullable()->after('name');
            });
        }

        if (! Schema::hasColumn('item_categories', 'is_active')) {
            Schema::table('item_categories', function (Blueprint $table): void {
                $table->boolean('is_active')->default(true)->after('status');
            });
        }

        if (! Schema::hasColumn('item_categories', 'sort_order')) {
            Schema::table('item_categories', function (Blueprint $table): void {
                $table->integer('sort_order')->default(0)->after('is_active');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('item_categories')) {
            return;
        }

        Schema::table('item_categories', function (Blueprint $table): void {
            foreach (['sort_order', 'is_active', 'description'] as $column) {
                if (Schema::hasColumn('item_categories', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
