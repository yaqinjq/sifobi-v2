<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('goods_receipts', function (Blueprint $table) {
            $table->timestamp('receipt_synced_at')->nullable()->after('approved_at');
            $table->text('receipt_sync_error')->nullable()->after('receipt_synced_at');
        });
    }

    public function down(): void
    {
        Schema::table('goods_receipts', function (Blueprint $table) {
            $table->dropColumn(['receipt_synced_at', 'receipt_sync_error']);
        });
    }
};
