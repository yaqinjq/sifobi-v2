<?php

namespace Database\Seeders;

use App\Models\AppSetting;
use App\Models\User;
use App\Modules\Core\Models\Brand;
use App\Modules\Core\Models\Department;
use App\Modules\Core\Models\Group;
use App\Modules\Core\Models\IntegrationProfile;
use App\Modules\Core\Models\LegalEntity;
use App\Modules\Core\Models\Outlet;
use App\Modules\Core\Models\Tenant;
use App\Modules\Inventory\Models\Item;
use App\Modules\Inventory\Models\ItemAlias;
use App\Modules\Inventory\Models\ItemCategory;
use App\Modules\Inventory\Models\ItemJenis;
use App\Modules\Inventory\Models\Unit;
use App\Modules\Receiving\Models\Supplier;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class MinimumMasterDataSeeder extends Seeder
{
    /**
     * Seed the minimum usable SIFOBI master data.
     */
    public function run(): void
    {
        $tenant = Tenant::query()->updateOrCreate(
            ['code' => 'MKO'],
            ['name' => 'MKO Group', 'status' => 'ACTIVE']
        );

        $appName = env('APP_NAME', 'SIFOBI');
        AppSetting::withoutGlobalScopes()->updateOrCreate(
            ['tenant_id' => $tenant->id],
            [
                'app_name' => $appName === 'Laravel' ? 'SIFOBI' : $appName,
                'app_tagline' => 'Food & Beverage Inventory System',
                'primary_color' => '#1B4332',
            ]
        );

        ItemJenisSeeder::seedForTenant((int) $tenant->id);
        ItemCategorySeeder::seedForTenant((int) $tenant->id);

        $group = Group::query()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'code' => 'MKO_GROUP'],
            ['name' => 'MKO Group', 'status' => 'ACTIVE']
        );

        $legalEntities = collect([
            'PT_MKO_001' => 'PT MKO 001',
            'PT_MKO_002' => 'PT MKO 002',
        ])->mapWithKeys(fn (string $name, string $code): array => [
            $code => LegalEntity::query()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'code' => $code],
                ['group_id' => $group->id, 'name' => $name, 'status' => 'ACTIVE']
            ),
        ]);

        $brands = collect([
            'MYKOPIO' => 'MyKopio',
            'QUALI' => 'Quali',
        ])->mapWithKeys(fn (string $name, string $code): array => [
            $code => Brand::query()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'code' => $code],
                ['group_id' => $group->id, 'name' => $name, 'status' => 'ACTIVE']
            ),
        ]);

        $outletOne = Outlet::query()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'code' => 'MKO_OUTLET_1'],
            [
                'brand_id' => $brands['MYKOPIO']->id,
                'legal_entity_id' => $legalEntities['PT_MKO_001']->id,
                'name' => 'MKO Outlet 1',
                'outlet_type' => 'OUTLET',
                'timezone' => 'Asia/Jakarta',
                'status' => 'ACTIVE',
            ]
        );

        $outletTwo = Outlet::query()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'code' => 'MKO_OUTLET_2'],
            [
                'brand_id' => $brands['QUALI']->id,
                'legal_entity_id' => $legalEntities['PT_MKO_002']->id,
                'name' => 'MKO Outlet 2',
                'outlet_type' => 'OUTLET',
                'timezone' => 'Asia/Jakarta',
                'status' => 'ACTIVE',
            ]
        );

        foreach (['BAR', 'KITCHEN', 'SERVICE', 'OFFICE', 'PASTRY'] as $departmentCode) {
            Department::query()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'code' => $departmentCode],
                ['name' => $departmentCode, 'is_operational' => true, 'status' => 'ACTIVE']
            );
        }

        Department::query()
            ->where('tenant_id', $tenant->id)
            ->where('code', 'GUDANG')
            ->update(['status' => 'INACTIVE']);

        $rawMaterialJenis = ItemJenis::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('code', 'RAW_MATERIAL')
            ->first();

        $bumbuCategory = ItemCategory::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('code', 'DRY_HERBS')
            ->first();

        $milkCategory = ItemCategory::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('code', 'MILK')
            ->first();

        $units = collect([
            'GR' => ['Gram', 'gr', 3],
            'KG' => ['Kilogram', 'kg', 3],
            'ML' => ['Milliliter', 'ml', 3],
            'L' => ['Liter', 'l', 3],
            'PCS' => ['Pieces', 'pcs', 0],
            'PACK' => ['Pack', 'pack', 0],
            'DUS' => ['Dus', 'dus', 0],
        ])->mapWithKeys(fn (array $unit, string $code): array => [
            $code => Unit::query()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'code' => $code],
                ['name' => $unit[0], 'abbreviation' => $unit[1], 'decimal_places' => $unit[2], 'status' => 'ACTIVE']
            ),
        ]);

        foreach ([
            'SUP-001' => ['Supplier Umum', '-'],
            'SUP-OCIA' => ['OCIA Roastery (Internal)', '-'],
            'SUP-CK' => ['Central Kitchen (Internal)', '-'],
        ] as $code => [$name, $phone]) {
            Supplier::query()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'code' => $code],
                ['name' => $name, 'phone' => $phone, 'is_active' => true]
            );
        }

        IntegrationProfile::withoutGlobalScopes()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'code' => 'OCIA'],
            [
                'tenant_id' => $tenant->id,
                'provider' => 'OCIA',
                'name' => 'OCIA - Roastery Kopi (OMEO)',
                'base_url' => env('OCIA_BASE_URL', 'https://ocia.mykopiogroup.com'),
                'auth_mode' => 'BEARER',
                'auth_type' => 'BEARER',
                'auth_token' => env('OCIA_API_TOKEN', ''),
                'api_token' => env('OCIA_API_TOKEN', ''),
                'auth_username' => null,
                'username' => null,
                'auth_password' => null,
                'password' => null,
                'outlet_sync_path' => '/api/outlets',
                'is_active' => true,
                'meta' => [
                    'health_path' => '/api/health',
                    'outlet_list_path' => '/api/outlets',
                    'po_list_path' => '/api/available-orders',
                    'order_path' => '/api/outlet-order',
                    'timeout_seconds' => 10,
                ],
            ]
        );

        $ajinomoto = Item::query()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'canonical_sku' => 'MKO-AJINOMOTO-500GR'],
            [
                'inventory_unit_id' => $units['GR']->id,
                'purchase_unit_id' => $units['PACK']->id,
                'base_unit_id' => $units['GR']->id,
                'inventory_ratio' => '500.000000',
                'name' => 'Ajinomoto 500gr',
                'description' => 'Bumbu penyedap',
                'item_type' => 'BAHAN_BAKU',
                'item_jenis_id' => $rawMaterialJenis?->id,
                'item_category_id' => $bumbuCategory?->id,
                'standard_cost' => 0,
                'purchase_ratio' => '12000.000000',
                'yield_pct' => '100.00',
                'last_purchase_price' => '25000.0000',
                'track_stock' => true,
                'is_active' => true,
            ]
        );

        $gulaPasir = Item::query()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'canonical_sku' => 'MKO-GULA-PASIR'],
            [
                'inventory_unit_id' => $units['GR']->id,
                'purchase_unit_id' => $units['KG']->id,
                'base_unit_id' => $units['GR']->id,
                'inventory_ratio' => '1000.000000',
                'name' => 'Gula Pasir',
                'description' => 'Gula pasir putih',
                'item_type' => 'BAHAN_BAKU',
                'item_jenis_id' => $rawMaterialJenis?->id,
                'item_category_id' => $bumbuCategory?->id,
                'standard_cost' => 0,
                'purchase_ratio' => '1000.000000',
                'yield_pct' => '100.00',
                'last_purchase_price' => null,
                'track_stock' => true,
                'is_active' => true,
            ]
        );

        $susuUht = Item::query()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'canonical_sku' => 'MKO-SUSU-UHT'],
            [
                'inventory_unit_id' => $units['ML']->id,
                'purchase_unit_id' => $units['PACK']->id,
                'base_unit_id' => $units['ML']->id,
                'inventory_ratio' => '1000.000000',
                'name' => 'Susu UHT',
                'description' => 'Susu UHT',
                'item_type' => 'BAHAN_BAKU',
                'item_jenis_id' => $rawMaterialJenis?->id,
                'item_category_id' => $milkCategory?->id,
                'standard_cost' => 0,
                'purchase_ratio' => '1000.000000',
                'yield_pct' => '100.00',
                'last_purchase_price' => null,
                'track_stock' => true,
                'is_active' => true,
            ]
        );

        foreach (['AJI-MKO-500', 'AJINOMOTO-QALI-01'] as $aliasCode) {
            ItemAlias::query()->updateOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'source_system' => 'LEGACY',
                    'alias_type' => 'SKU',
                    'alias_code' => $aliasCode,
                ],
                [
                    'item_id' => $ajinomoto->id,
                    'brand_id' => null,
                    'outlet_id' => null,
                    'alias_name' => 'Ajinomoto 500gr',
                ]
            );
        }

        foreach ([$ajinomoto, $gulaPasir, $susuUht] as $item) {
            foreach ([$outletOne, $outletTwo] as $outlet) {
                $item->outlets()->syncWithoutDetaching([
                    $outlet->id => [
                        'tenant_id' => $tenant->id,
                        'status' => 'ACTIVE',
                        'opname_frequency' => 'DAILY',
                        'is_active' => true,
                        'unit_id' => $item->inventory_unit_id ?: $item->base_unit_id,
                    ],
                ]);
            }
        }

        if (! app()->isProduction()) {
            $superAdmin = User::query()->updateOrCreate(
                ['email' => 'admin@sifobi.test'],
                [
                    'tenant_id' => $tenant->id,
                    'outlet_id' => null,
                    'department_id' => null,
                    'name' => 'Super Admin',
                    'password' => Hash::make('password'),
                    'status' => 'ACTIVE',
                ]
            );

            $superAdmin->assignRole('SUPER_ADMIN');
        }
    }
}
