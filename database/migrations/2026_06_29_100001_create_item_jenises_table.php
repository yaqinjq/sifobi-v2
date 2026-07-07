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
        if (! Schema::hasTable('item_jenises')) {
            Schema::create('item_jenises', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('tenant_id')
                    ->constrained(indexName: 'fk_item_jenises_tenant')
                    ->cascadeOnDelete();
                $table->string('code', 50);
                $table->string('name', 150);
                $table->string('color', 20)->default('gray');
                $table->text('description')->nullable();
                $table->boolean('is_active')->default(true);
                $table->integer('sort_order')->default(0);
                $table->timestamps();

                $table->unique(['tenant_id', 'code'], 'uq_item_jenises_code');
                $table->index('tenant_id', 'idx_item_jenises_tenant');
            });
        }

        if (Schema::hasTable('items') && ! Schema::hasColumn('items', 'item_jenis_id')) {
            Schema::table('items', function (Blueprint $table): void {
                $afterColumn = Schema::hasColumn('items', 'item_jenis') ? 'item_jenis' : 'item_type';

                $table->foreignId('item_jenis_id')
                    ->nullable()
                    ->after($afterColumn)
                    ->constrained(table: 'item_jenises', indexName: 'fk_items_item_jenis')
                    ->nullOnDelete();

                $table->index(['tenant_id', 'item_jenis_id'], 'idx_items_tenant_jenis');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('items') && Schema::hasColumn('items', 'item_jenis_id')) {
            Schema::table('items', function (Blueprint $table): void {
                $table->dropForeign('fk_items_item_jenis');
                $table->dropIndex('idx_items_tenant_jenis');
                $table->dropColumn('item_jenis_id');
            });
        }

        Schema::dropIfExists('item_jenises');
    }
};
