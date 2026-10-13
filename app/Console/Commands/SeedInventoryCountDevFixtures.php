<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Modules\Core\Models\Department;
use App\Modules\Core\Models\Outlet;
use App\Modules\Core\Models\Tenant;
use Database\Seeders\MinimumMasterDataSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Tests\Fixtures\InventoryCountSyntheticFixtures;

/**
 * Dev-only one-off command (TIDAK untuk produksi) -- seed database sqlite
 * worktree ISOLATED ini dengan master data minimum + fixture sintetis
 * penuh (307 baris referensi, 5 template) untuk verifikasi visual
 * Playwright. Dipanggil manual, bukan terdaftar di scheduler apa pun.
 */
class SeedInventoryCountDevFixtures extends Command
{
    protected $signature = 'inventory-count:seed-dev-fixtures';

    protected $description = 'Seed isolated dev sqlite with full synthetic Inventory Simple fixtures (NOT for production)';

    public function handle(): int
    {
        // Allow-list, BUKAN deny-list -- app()->isProduction() sebelumnya
        // cuma menolak APP_ENV='production' secara eksplisit, artinya
        // APP_ENV='staging' (atau environment lain yang mungkin menyimpan
        // data riil) LOLOS tanpa dicegah sama sekali. Command ini menulis
        // data sintetis massal (307 baris fixture) -- HANYA boleh jalan
        // di local/testing yang sudah pasti terisolasi.
        if (! app()->environment(['local', 'testing'])) {
            $this->error('Refused: this command only runs in local/testing environments (current: '.app()->environment().'). Never run this against staging or production.');

            return self::FAILURE;
        }

        $this->call(RolesAndPermissionsSeeder::class);
        app(MinimumMasterDataSeeder::class)->run();

        $tenant = Tenant::query()->where('code', 'MKO')->firstOrFail();
        $outlet = Outlet::query()->where('code', 'MKO_OUTLET_1')->firstOrFail();
        $bar = Department::query()->where('code', 'BAR')->firstOrFail();
        $kitchen = Department::query()->where('code', 'KITCHEN')->firstOrFail();

        $result = (new InventoryCountSyntheticFixtures($tenant))->build();

        foreach ([
            ['email' => 'bar_staff@sifobi.test', 'dept' => $bar, 'role' => 'STAFF_BAR'],
            ['email' => 'kitchen_staff@sifobi.test', 'dept' => $kitchen, 'role' => 'STAFF_KITCHEN'],
        ] as $u) {
            $user = User::query()->updateOrCreate(
                ['email' => $u['email']],
                [
                    'tenant_id' => $tenant->id,
                    'outlet_id' => $outlet->id,
                    'department_id' => $u['dept']->id,
                    'name' => $u['role'],
                    'password' => Hash::make('TestInv123!'),
                    'status' => 'ACTIVE',
                    'email_verified_at' => now(),
                ]
            );
            $user->syncRoles([$u['role']]);
        }

        foreach ($result['templates'] as $code => $template) {
            $this->line("$code: ".$template->items()->count().' template items');
        }
        $this->line('Skipped (unit unresolved): '.count($result['skipped_unit_unresolved']));

        return self::SUCCESS;
    }
}
