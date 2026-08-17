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

        $permissions = [
            Permission::firstOrCreate(['name' => 'manage_recipes', 'guard_name' => 'web']),
            Permission::firstOrCreate(['name' => 'approve_recipes', 'guard_name' => 'web']),
        ];

        // PIC ke atas boleh RnD & approve resep — bukan role STAFF_*.
        $rolesWithRecipeAccess = [
            'SUPER_ADMIN',
            'ADMIN',
            'GENERAL_FINANCE',
            'MANAGER_AREA',
            'PIC_OUTLET',
        ];

        foreach ($rolesWithRecipeAccess as $roleName) {
            $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();

            if (! $role) {
                continue;
            }

            foreach ($permissions as $permission) {
                if (! $role->hasPermissionTo($permission)) {
                    $role->givePermissionTo($permission);
                }
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::whereIn('name', ['manage_recipes', 'approve_recipes'])->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
