<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->timestamp('wipro_shipped_at')->nullable()->after('external_sync_error');
            $table->string('wipro_dispatch_number')->nullable()->after('wipro_shipped_at');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropColumn(['wipro_shipped_at', 'wipro_dispatch_number']);
        });
    }
};
