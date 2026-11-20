<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Spoil sekarang bisa mencatat MENU jadi (tabel `menus`), bukan cuma bahan
 * baku (`items`). Tepat satu dari item_id/menu_id terisi -- dijaga di
 * aplikasi (SpoilWasteController::validatedBatch() + SpoilWasteService),
 * bukan constraint DB.
 *
 * down() mengembalikan item_id ke NOT NULL -- cuma aman kalau belum ada
 * baris spoil menu (item_id null); hapus/konversi baris itu dulu.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('spoil_wastes', function (Blueprint $table): void {
            $table->foreignId('menu_id')
                ->nullable()
                ->after('item_id')
                ->constrained(table: 'menus', indexName: 'fk_spoil_wastes_menu')
                ->restrictOnDelete();

            $table->unsignedBigInteger('item_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('spoil_wastes', function (Blueprint $table): void {
            $table->dropForeign('fk_spoil_wastes_menu');
            $table->dropColumn('menu_id');
            $table->unsignedBigInteger('item_id')->nullable(false)->change();
        });
    }
};
