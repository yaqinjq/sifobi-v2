<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Buat index baru DULU sebelum drop yang lama.
        // uq_opname_items_scope dipakai MySQL sebagai supporting index untuk FK fk_opname_items_tenant
        // (karena tenant_id ada di kolom pertama). Jika di-drop duluan, MySQL error.
        Schema::table('opname_items', function (Blueprint $table): void {
            $table->unique(
                ['tenant_id', 'opname_session_id', 'item_id', 'department_id'],
                'uq_opname_items_scope_dept'
            );
        });

        Schema::table('opname_items', function (Blueprint $table): void {
            $table->dropUnique('uq_opname_items_scope');
        });
    }

    public function down(): void
    {
        Schema::table('opname_items', function (Blueprint $table): void {
            $table->unique(
                ['tenant_id', 'opname_session_id', 'item_id'],
                'uq_opname_items_scope'
            );
        });

        Schema::table('opname_items', function (Blueprint $table): void {
            $table->dropUnique('uq_opname_items_scope_dept');
        });
    }
};
