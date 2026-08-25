<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('app_settings', function (Blueprint $table): void {
            $table->decimal('loyalty_points_per_amount', 19, 4)->nullable();
            $table->decimal('loyalty_point_value', 19, 4)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('app_settings', function (Blueprint $table): void {
            $table->dropColumn(['loyalty_points_per_amount', 'loyalty_point_value']);
        });
    }
};
