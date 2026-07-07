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
        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'tenant_id')) {
                $table->foreignId('tenant_id')
                    ->nullable()
                    ->after('id')
                    ->constrained(indexName: 'fk_users_tenant')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('users', 'outlet_id')) {
                $table->foreignId('outlet_id')
                    ->nullable()
                    ->after('tenant_id')
                    ->constrained(indexName: 'fk_users_outlet')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('users', 'phone')) {
                $table->string('phone', 20)->nullable()->after('email');
            }

            if (! Schema::hasColumn('users', 'photo')) {
                $table->string('photo')->nullable()->after('phone');
            }

            if (! Schema::hasColumn('users', 'status')) {
                $table->string('status', 24)->default('ACTIVE')->after('photo')->index('idx_users_status');
            }

            if (! Schema::hasColumn('users', 'last_login_at')) {
                $table->dateTime('last_login_at')->nullable()->after('remember_token');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            foreach (['last_login_at', 'photo', 'phone'] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
