<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            $table->string('subdomain', 63)->nullable()->unique('uq_tenants_subdomain')->after('code');
            $table->string('custom_domain', 191)->nullable()->unique('uq_tenants_custom_domain')->after('subdomain');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            $table->dropUnique('uq_tenants_subdomain');
            $table->dropUnique('uq_tenants_custom_domain');
            $table->dropColumn(['subdomain', 'custom_domain']);
        });
    }
};
