<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Urutan tampil ITEM di dalam 1 sub kategori, per departemen -- pelengkap
 * item_department_categories (yang cuma mengatur kategori utama & sub
 * kategori, bukan urutan antar-item di dalamnya). Sengaja OPSIONAL/sparse:
 * item yang tidak ada baris di sini jatuh ke urutan alfabetis di BELAKANG
 * item yang sudah diurutkan secara eksplisit (lihat Item::formSortKey()) --
 * supaya bisa diisi bertahap per sub kategori tanpa merusak urutan item
 * lain yang belum sempat di-mapping.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('item_department_sort_orders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('item_id')->constrained(indexName: 'fk_item_dept_sort_item')->cascadeOnDelete();
            $table->foreignId('department_id')->constrained(indexName: 'fk_item_dept_sort_dept')->cascadeOnDelete();
            $table->unsignedInteger('sort_order');
            $table->timestamps();

            $table->unique(['item_id', 'department_id'], 'uq_item_dept_sort_scope');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('item_department_sort_orders');
    }
};
