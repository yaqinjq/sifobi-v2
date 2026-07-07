<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('brands')) {
            if (! Schema::hasColumn('brands', 'logo_path')) {
                Schema::table('brands', function (Blueprint $table): void {
                    $table->string('logo_path')->nullable()->after('name');
                });
            }

            if (! Schema::hasColumn('brands', 'description')) {
                Schema::table('brands', function (Blueprint $table): void {
                    $table->text('description')->nullable()->after('logo_path');
                });
            }
        }

        if (Schema::hasTable('outlets') && ! Schema::hasColumn('outlets', 'contact_phone')) {
            Schema::table('outlets', function (Blueprint $table): void {
                $table->string('contact_phone', 50)->nullable()->after('address');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('outlets') && Schema::hasColumn('outlets', 'contact_phone')) {
            Schema::table('outlets', function (Blueprint $table): void {
                $table->dropColumn('contact_phone');
            });
        }

        if (Schema::hasTable('brands')) {
            Schema::table('brands', function (Blueprint $table): void {
                foreach (['description', 'logo_path'] as $column) {
                    if (Schema::hasColumn('brands', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
