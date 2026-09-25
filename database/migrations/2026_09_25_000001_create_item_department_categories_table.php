<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Override kategori PER DEPARTEMEN untuk item yang dipakai lintas
 * departemen dengan kebutuhan kategori berbeda (mis. Tepung Maizena =
 * kategori "Other" di BAR, tapi "Rice & Flour" di PASTRY/KITCHEN).
 * Sengaja OPSIONAL/sparse: mayoritas item yang cuma butuh 1 kategori sama
 * di semua departemen tidak perlu baris apa pun di sini, tetap pakai
 * items.item_category_id seperti biasa -- lihat Item::categoryForDepartment().
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('item_department_categories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('item_id')->constrained(indexName: 'fk_item_dept_cats_item')->cascadeOnDelete();
            $table->foreignId('department_id')->constrained(indexName: 'fk_item_dept_cats_dept')->cascadeOnDelete();
            $table->foreignId('item_category_id')->constrained('item_categories', indexName: 'fk_item_dept_cats_category')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['item_id', 'department_id'], 'uq_item_dept_cats_scope');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('item_department_categories');
    }
};
