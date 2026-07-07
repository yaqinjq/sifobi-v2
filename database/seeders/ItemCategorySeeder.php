<?php

namespace Database\Seeders;

use App\Modules\Core\Models\Tenant;
use App\Modules\Inventory\Models\ItemCategory;
use Illuminate\Database\Seeder;

class ItemCategorySeeder extends Seeder
{
    /**
     * Seed item categories for every tenant.
     */
    public function run(): void
    {
        Tenant::query()
            ->orderBy('id')
            ->each(fn (Tenant $tenant) => self::seedForTenant((int) $tenant->id));
    }

    public static function seedForTenant(int $tenantId): void
    {
        $categories = [
            ['code' => 'COFFEE_TEA', 'name' => 'Coffee & Tea'],
            ['code' => 'MILK', 'name' => 'Milk & Dairy'],
            ['code' => 'FRUIT_VEG', 'name' => 'Fruit & Vegetable'],
            ['code' => 'DRY_FRUITS_SEEDS', 'name' => 'Dry Fruits & Seeds'],
            ['code' => 'SYRUP_JAM', 'name' => 'Syrup & Jam'],
            ['code' => 'POWDER', 'name' => 'Powder'],
            ['code' => 'CAN_BOTTLE', 'name' => 'Can & Bottle'],
            ['code' => 'BASED_WIP', 'name' => 'Based WIP'],
            ['code' => 'OTHER', 'name' => 'Other'],
            ['code' => 'PACKAGING', 'name' => 'Packaging'],
            ['code' => 'CHEMICAL', 'name' => 'Chemical'],
            ['code' => 'PLASTIC', 'name' => 'Plastic'],
            ['code' => 'DRY_CAN_VEG', 'name' => 'Dry Can Vegetable'],
            ['code' => 'OIL', 'name' => 'Oil'],
            ['code' => 'RICE_FLOUR', 'name' => 'Rice & Flour'],
            ['code' => 'SAUCES', 'name' => 'Sauces'],
            ['code' => 'DRY_HERBS', 'name' => 'Dry Herbs'],
            ['code' => 'MEAT_SEAFOOD', 'name' => 'Meat & Seafood'],
        ];

        foreach ($categories as $index => $category) {
            ItemCategory::withoutGlobalScopes()->updateOrCreate(
                ['tenant_id' => $tenantId, 'code' => $category['code']],
                array_merge($category, [
                    'tenant_id' => $tenantId,
                    'status' => 'ACTIVE',
                    'is_active' => true,
                    'sort_order' => $index + 1,
                ])
            );
        }
    }
}
