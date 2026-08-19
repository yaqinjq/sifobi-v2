<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * manage_tenants — khusus pemilik platform (SUPER_ADMIN tenant MKO),
     * mengatur /admin/tenants (daftar & domain SEMUA tenant). SENGAJA
     * tidak diberikan ke role ADMIN biasa — beda dari pola permission
     * lain di app ini yang selalu ADMIN juga dapat — karena ADMIN adalah
     * role standar yang di-seed ulang untuk SETIAP tenant baru (lihat
     * TenantRoleSeeder), dan tenant lain tidak boleh bisa mengelola
     * domain tenant lain.
     */
    public function up(): void
    {
        $registrar = app(PermissionRegistrar::class);
        $registrar->forgetCachedPermissions();

        $permission = Permission::firstOrCreate(['name' => 'manage_tenants', 'guard_name' => 'web']);

        // Role SUPER_ADMIN milik tenant MKO (team_id=1) — bukan SUPER_ADMIN
        // tenant lain (kalaupun ada), karena manage_tenants cuma untuk
        // pemilik platform.
        $registrar->setPermissionsTeamId(1);

        $role = Role::where('team_id', 1)->where('name', 'SUPER_ADMIN')->where('guard_name', 'web')->first();

        if ($role && ! $role->hasPermissionTo($permission)) {
            $role->givePermissionTo($permission);
        }

        $registrar->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::where('name', 'manage_tenants')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
