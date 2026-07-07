<?php

namespace Database\Seeders;

use App\Modules\Core\Models\Tenant;
use App\Modules\Inventory\Models\ItemJenis;
use Illuminate\Database\Seeder;

class ItemJenisSeeder extends Seeder
{
    public const JENISES = [
        ['code' => 'DRYGOOD', 'name' => 'Dry Good', 'color' => 'amber'],
        ['code' => 'MENU', 'name' => 'Menu Item', 'color' => 'rose'],
        ['code' => 'NON_RAW_MATERIAL', 'name' => 'Non Raw Material', 'color' => 'blue'],
        ['code' => 'RAW_MATERIAL', 'name' => 'Raw Material', 'color' => 'green'],
        ['code' => 'WIP', 'name' => 'Work In Progress', 'color' => 'purple'],
    ];

    /**
     * Seed item jenis master data for all available tenants.
     */
    public function run(): void
    {
        Tenant::query()
            ->orderBy('id')
            ->get()
            ->each(fn (Tenant $tenant) => self::seedForTenant((int) $tenant->id));
    }

    public static function seedForTenant(int $tenantId): void
    {
        $sortOrder = 1;

        foreach (self::JENISES as $jenis) {
            ItemJenis::withoutGlobalScopes()->updateOrCreate(
                ['tenant_id' => $tenantId, 'code' => $jenis['code']],
                array_merge($jenis, [
                    'tenant_id' => $tenantId,
                    'is_active' => true,
                    'sort_order' => $sortOrder++,
                ])
            );
        }
    }
}
