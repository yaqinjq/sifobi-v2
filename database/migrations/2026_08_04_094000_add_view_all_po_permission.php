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

        $perm = Permission::firstOrCreate([
            'name'       => 'view_all_po',
            'guard_name' => 'web',
        ]);

        // Roles that should see POs across all departments
        $rolesWithViewAllPo = [
            'SUPER_ADMIN',
            'ADMIN',
            'GENERAL_FINANCE',
            'FINANCE_ACCOUNTING_STAFF',
            'MANAGER_AREA',
        ];

        foreach ($rolesWithViewAllPo as $roleName) {
            $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();
            if ($role && ! $role->hasPermissionTo('view_all_po')) {
                $role->givePermissionTo($perm);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::where('name', 'view_all_po')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
