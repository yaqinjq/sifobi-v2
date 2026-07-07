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
        if (! Schema::hasTable('integration_profiles')) {
            return;
        }

        Schema::table('integration_profiles', function (Blueprint $table): void {
            if (! Schema::hasColumn('integration_profiles', 'code')) {
                $table->string('code', 50)->nullable()->after('tenant_id');
            }

            if (! Schema::hasColumn('integration_profiles', 'auth_mode')) {
                $table->string('auth_mode', 30)->nullable()->after('base_url');
            }

            if (! Schema::hasColumn('integration_profiles', 'auth_token')) {
                $table->string('auth_token', 255)->nullable()->after('auth_mode');
            }

            if (! Schema::hasColumn('integration_profiles', 'auth_username')) {
                $table->string('auth_username', 255)->nullable()->after('auth_token');
            }

            if (! Schema::hasColumn('integration_profiles', 'auth_password')) {
                $table->string('auth_password', 255)->nullable()->after('auth_username');
            }
        });

        Schema::table('integration_profiles', function (Blueprint $table): void {
            $table->unique(['tenant_id', 'code'], 'uq_integrations_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('integration_profiles')) {
            return;
        }

        Schema::table('integration_profiles', function (Blueprint $table): void {
            $table->dropUnique('uq_integrations_code');
        });

        Schema::table('integration_profiles', function (Blueprint $table): void {
            foreach (['auth_password', 'auth_username', 'auth_token', 'auth_mode', 'code'] as $column) {
                if (Schema::hasColumn('integration_profiles', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
