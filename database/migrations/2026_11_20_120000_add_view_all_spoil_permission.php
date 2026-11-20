<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Permission baru `view_all_spoil`: role di atas PIC bisa MELIHAT spoil
 * semua outlet (yang boleh diakses akunnya) & semua departemen, lengkap
 * dgn filter outlet/departemen di halaman Spoil & Waste. Approve/tolak/
 * hapus TIDAK ikut melebar -- tetap aturan scope lama.
 *
 * Role dibuat per tenant (Spatie teams), jadi diberikan ke SEMUA role
 * dgn nama tsb di semua tenant -- bukan cuma ->first(). Ditambah role
 * custom "Asisten/Assistant Super Admin" yang dibuat user lewat
 * Settings > Role (namanya tidak pasti, jadi dicocokkan longgar). Role
 * lain bisa dicentang manual di Settings > Role.
 */
return new class extends Migration
{
    private const ROLES = ['SUPER_ADMIN', 'ADMIN', 'GENERAL_FINANCE', 'MANAGER_AREA'];

    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permission = Permission::firstOrCreate(['name' => 'view_all_spoil', 'guard_name' => 'web']);

        Role::query()
            ->where('guard_name', 'web')
            ->get()
            ->filter(function (Role $role): bool {
                $name = strtoupper($role->name);

                $isAssistantAdmin = str_contains($name, 'ADMIN')
                    && (str_contains($name, 'ASIS') || str_contains($name, 'ASSIS') || str_contains($name, 'ASST'));

                return in_array($name, self::ROLES, true) || $isAssistantAdmin;
            })
            ->each(function (Role $role) use ($permission): void {
                if (! $role->hasPermissionTo($permission)) {
                    $role->givePermissionTo($permission);
                }
            });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::where('name', 'view_all_spoil')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
