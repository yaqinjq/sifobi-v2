<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('menus', function (Blueprint $table): void {
            $table->string('photo_path')->nullable()->after('name');
            $table->foreignId('menu_category_id')->nullable()->after('brand_id')
                ->constrained('menu_categories', indexName: 'fk_menus_category')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('menus', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('menu_category_id');
            $table->dropColumn('photo_path');
        });
    }
};
