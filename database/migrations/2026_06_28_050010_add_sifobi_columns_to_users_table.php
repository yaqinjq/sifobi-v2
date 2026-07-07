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
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('tenant_id')->nullable()->after('id')->constrained(indexName: 'fk_users_tenant')->nullOnDelete();
            $table->foreignId('outlet_id')->nullable()->after('tenant_id')->constrained(indexName: 'fk_users_outlet')->nullOnDelete();
            $table->foreignId('department_id')->nullable()->after('outlet_id')->constrained(indexName: 'fk_users_department')->nullOnDelete();
            $table->string('status', 24)->default('ACTIVE')->after('password')->index('idx_users_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign('fk_users_tenant');
            $table->dropForeign('fk_users_outlet');
            $table->dropForeign('fk_users_department');
            $table->dropIndex('idx_users_status');
            $table->dropColumn(['tenant_id', 'outlet_id', 'department_id', 'status']);
        });
    }
};
