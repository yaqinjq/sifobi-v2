<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('goods_receipts', 'photo_invoice')) {
            return;
        }

        Schema::table('goods_receipts', function (Blueprint $table): void {
            $table->string('photo_invoice')->nullable()->after('photo_document');
        });
    }

    public function down(): void
    {
        Schema::table('goods_receipts', function (Blueprint $table): void {
            $table->dropColumn('photo_invoice');
        });
    }
};
