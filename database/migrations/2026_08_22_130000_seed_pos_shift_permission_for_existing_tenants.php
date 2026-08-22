<?php

use App\Models\Tenant;
use App\Services\TenantRoleSeeder;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\PermissionRegistrar;

/**
 * Sama seperti 2026_08_22_100000_seed_pos_permissions_for_existing_tenants:
 * permission baru (approve_pos_shift, plus manage_pos_layout/view_pos_reports/
 * void_pos_order yang ternyata belum sempat ke-grant ke GENERAL_FINANCE di
 * migration sebelumnya) tidak otomatis sampai ke tenant yang sudah ada hanya
 * lewat `artisan migrate` -- deploy.sh tidak pernah menjalankan seeder.
 */
return new class extends Migration
{
    public function up(): void
    {
        $seeder = app(TenantRoleSeeder::class);

        foreach (Tenant::query()->pluck('id') as $tenantId) {
            $seeder->seedForTenant((int) $tenantId);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Sengaja no-op -- lihat alasan yang sama di migration sebelumnya.
    }
};
