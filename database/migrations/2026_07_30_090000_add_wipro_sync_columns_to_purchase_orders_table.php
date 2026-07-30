<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table): void {
            $table->string('external_reference', 100)->nullable();
            $table->timestamp('external_synced_at')->nullable();
            $table->text('external_sync_error')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table): void {
            $table->dropColumn(['external_reference', 'external_synced_at', 'external_sync_error']);
        });
    }
};
