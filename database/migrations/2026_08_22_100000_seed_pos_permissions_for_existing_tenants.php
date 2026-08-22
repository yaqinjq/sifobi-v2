<?php

use App\Models\Tenant;
use App\Services\TenantRoleSeeder;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\PermissionRegistrar;

/**
 * Permission POS (operate_pos, manage_pos_layout, view_pos_reports,
 * void_pos_order) sudah ditambahkan ke TenantRoleSeeder, tapi
 * TenantRoleSeeder::seedForTenant() cuma dipanggil otomatis saat ONBOARDING
 * tenant baru (lihat Admin\TenantController::store()) — tenant yang SUDAH
 * ada (mis. MKO) tidak otomatis dapat permission baru ini hanya dari
 * `artisan migrate`. deploy.sh juga tidak pernah menjalankan seeder apapun
 * (cuma migrate --force), jadi migration ini yang memastikan semua tenant
 * existing ikut ter-update begitu deploy.sh jalan di server.
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
        // Sengaja no-op — menghapus permission POS dari semua role di semua
        // tenant lebih berisiko daripada gunanya, dan permission yang tidak
        // dipakai role manapun tidak berdampak apa-apa kalau dibiarkan.
    }
};
