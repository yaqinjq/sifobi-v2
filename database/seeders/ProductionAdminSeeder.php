<?php

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Core\Models\Outlet;
use App\Modules\Core\Models\Tenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class ProductionAdminSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::withoutGlobalScopes()
            ->where('code', 'MKO')
            ->firstOrFail();

        $adminPassword = env('SIFOBI_PROD_ADMIN_PASSWORD');
        $staffPassword = env('SIFOBI_PROD_STAFF_PASSWORD');

        if (! $adminPassword || ! $staffPassword) {
            throw new RuntimeException('Set SIFOBI_PROD_ADMIN_PASSWORD and SIFOBI_PROD_STAFF_PASSWORD in .env before running ProductionAdminSeeder.');
        }

        $admin = User::query()->updateOrCreate(
            ['email' => 'admin@mykopiogroup.com'],
            [
                'name' => 'System Administrator',
                'password' => Hash::make($adminPassword),
                'tenant_id' => $tenant->id,
                'outlet_id' => null,
                'department_id' => null,
                'status' => 'ACTIVE',
            ]
        );
        $admin->syncRoles(['SUPER_ADMIN']);

        $outlet = Outlet::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('status', 'ACTIVE')
            ->orderBy('id')
            ->first();

        if ($outlet) {
            $staffBar = User::query()->updateOrCreate(
                ['email' => 'staff.bar@mykopiogroup.com'],
                    [
                        'name' => 'Staff Bar Test',
                        'password' => Hash::make($staffPassword),
                    'tenant_id' => $tenant->id,
                    'outlet_id' => $outlet->id,
                    'department_id' => null,
                    'status' => 'ACTIVE',
                ]
            );
            $staffBar->syncRoles(['STAFF_BAR']);
        }
    }
}
