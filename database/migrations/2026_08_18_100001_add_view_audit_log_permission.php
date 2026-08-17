<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permission = Permission::firstOrCreate(['name' => 'view_audit_log', 'guard_name' => 'web']);

        // Level oversight/kepatuhan saja — bukan PIC_OUTLET atau role STAFF_*.
        $rolesWithAuditAccess = [
            'SUPER_ADMIN',
            'ADMIN',
            'GENERAL_FINANCE',
            'MANAGER_AREA',
        ];

        foreach ($rolesWithAuditAccess as $roleName) {
            $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();

            if (! $role) {
                continue;
            }

            if (! $role->hasPermissionTo($permission)) {
                $role->givePermissionTo($permission);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::where('name', 'view_audit_log')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
