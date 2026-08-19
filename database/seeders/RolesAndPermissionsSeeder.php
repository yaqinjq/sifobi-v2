<?php

namespace Database\Seeders;

use App\Services\TenantRoleSeeder;
use Illuminate\Database\Seeder;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Seed role & permission standar untuk tenant MKO (id=1).
     *
     * Daftar role/permission-nya sendiri ada di TenantRoleSeeder — supaya
     * satu sumber kebenaran yang sama dipakai untuk seed tenant baru saat
     * onboarding (lihat Admin\TenantController::store()).
     *
     * PENTING: `manage_tenants` (khusus SUPER_ADMIN, lihat migration
     * add_manage_tenants_permission) SENGAJA tidak ada di TenantRoleSeeder
     * — syncPermissions() di bawah ini akan MENGHAPUS manage_tenants dari
     * SUPER_ADMIN kalau seeder ini dijalankan ulang. Kalau itu terjadi,
     * beri lagi manual: Role SUPER_ADMIN (team_id=1) -> givePermissionTo('manage_tenants').
     */
    public function run(): void
    {
        app(TenantRoleSeeder::class)->seedForTenant(1);
    }
}
