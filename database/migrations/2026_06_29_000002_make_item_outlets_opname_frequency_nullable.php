<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('item_outlets') || ! Schema::hasColumn('item_outlets', 'opname_frequency')) {
            return;
        }

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE item_outlets MODIFY opname_frequency VARCHAR(24) NULL DEFAULT NULL');

            return;
        }

        Schema::table('item_outlets', function (Blueprint $table): void {
            $table->string('opname_frequency', 24)->nullable()->default(null)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('item_outlets') || ! Schema::hasColumn('item_outlets', 'opname_frequency')) {
            return;
        }

        DB::table('item_outlets')
            ->whereNull('opname_frequency')
            ->update(['opname_frequency' => 'DAILY']);

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE item_outlets MODIFY opname_frequency VARCHAR(24) NOT NULL DEFAULT 'DAILY'");

            return;
        }

        Schema::table('item_outlets', function (Blueprint $table): void {
            $table->string('opname_frequency', 24)->default('DAILY')->nullable(false)->change();
        });
    }
};
