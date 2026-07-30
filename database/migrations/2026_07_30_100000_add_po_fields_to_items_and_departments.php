<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->json('po_destinations')->nullable()->after('is_active');
        });

        Schema::table('departments', function (Blueprint $table) {
            $table->json('allowed_po_types')->nullable()->after('is_operational');
        });
    }

    public function down(): void
    {
        Schema::table('departments', function (Blueprint $table) {
            $table->dropColumn('allowed_po_types');
        });

        Schema::table('items', function (Blueprint $table) {
            $table->dropColumn('po_destinations');
        });
    }
};
