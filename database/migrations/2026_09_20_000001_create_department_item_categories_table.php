<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('department_item_categories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('department_id')->constrained(indexName: 'fk_dept_item_cats_dept')->cascadeOnDelete();
            $table->foreignId('item_category_id')->constrained('item_categories', indexName: 'fk_dept_item_cats_category')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['department_id', 'item_category_id'], 'uq_dept_item_cats_scope');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('department_item_categories');
    }
};
