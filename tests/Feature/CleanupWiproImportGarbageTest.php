<?php

use App\Modules\Inventory\Models\Item;
use App\Modules\Inventory\Models\ItemCategory;
use App\Modules\Inventory\Models\ItemJenis;
use App\Modules\Inventory\Models\Unit;
use App\Modules\Core\Models\Tenant;
use Database\Seeders\MinimumMasterDataSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed([
        RolesAndPermissionsSeeder::class,
        MinimumMasterDataSeeder::class,
    ]);

    $this->tenant = Tenant::query()->where('code', 'MKO')->firstOrFail();

    $unit = Unit::query()->where('tenant_id', $this->tenant->id)->first();
    $jenis = ItemJenis::query()->where('tenant_id', $this->tenant->id)->first();

    $this->garbageCategory = ItemCategory::query()->create([
        'tenant_id' => $this->tenant->id,
        'code' => 'WIPRO_1_TEST',
        'name' => 'Wipro — 1',
        'status' => 'ACTIVE',
        'is_active' => true,
    ]);

    $this->legitCategory = ItemCategory::query()->create([
        'tenant_id' => $this->tenant->id,
        'code' => 'WIPRO_BUMBU_TEST',
        'name' => 'Wipro — Bumbu',
        'status' => 'ACTIVE',
        'is_active' => true,
    ]);

    $this->garbageItem = Item::query()->create([
        'tenant_id' => $this->tenant->id,
        'canonical_sku' => 'KWIP0546',
        'name' => 'BMB-GADO',
        'item_type' => 'WIP_L1',
        'item_jenis_id' => $jenis?->id,
        'item_category_id' => $this->garbageCategory->id,
        'base_unit_id' => $unit->id,
        'inventory_unit_id' => $unit->id,
        'purchase_unit_id' => $unit->id,
        'inventory_ratio' => '1.000000',
        'purchase_ratio' => '1.000000',
        'is_active' => false,
        'track_stock' => false,
        'item_source' => 'WIPRO',
        'opname_frequency' => 'DAILY',
        'min_stock' => '0.000000',
        'standard_cost' => '0.0000',
    ]);

    $this->legitItem = Item::query()->create([
        'tenant_id' => $this->tenant->id,
        'canonical_sku' => 'BMB-GADO',
        'name' => 'BUMBU GADO-GADO',
        'item_type' => 'WIP_L1',
        'item_jenis_id' => $jenis?->id,
        'item_category_id' => $this->legitCategory->id,
        'base_unit_id' => $unit->id,
        'inventory_unit_id' => $unit->id,
        'purchase_unit_id' => $unit->id,
        'inventory_ratio' => '1.000000',
        'purchase_ratio' => '1.000000',
        'is_active' => true,
        'track_stock' => false,
        'item_source' => 'WIPRO',
        'opname_frequency' => 'DAILY',
        'min_stock' => '0.000000',
        'standard_cost' => '0.0000',
    ]);
});

test('dry run reports garbage items without deleting anything', function (): void {
    $this->artisan("wipro:cleanup-garbage {$this->tenant->id}")
        ->expectsOutputToContain('DRY RUN')
        ->expectsOutputToContain('KWIP0546')
        ->assertExitCode(0);

    expect(Item::query()->whereKey($this->garbageItem->id)->exists())->toBeTrue()
        ->and(ItemCategory::query()->whereKey($this->garbageCategory->id)->exists())->toBeTrue();
});

test('force deletes only garbage category items and leaves legit wipro categories untouched', function (): void {
    $this->artisan("wipro:cleanup-garbage {$this->tenant->id} --force")
        ->assertExitCode(0);

    expect(Item::query()->whereKey($this->garbageItem->id)->exists())->toBeFalse()
        ->and(ItemCategory::query()->whereKey($this->garbageCategory->id)->exists())->toBeFalse()
        ->and(Item::query()->whereKey($this->legitItem->id)->exists())->toBeTrue()
        ->and(ItemCategory::query()->whereKey($this->legitCategory->id)->exists())->toBeTrue();
});

test('force skips a garbage item that is still referenced elsewhere and keeps its category', function (): void {
    $outlet = \App\Modules\Core\Models\Outlet::query()->where('tenant_id', $this->tenant->id)->firstOrFail();

    DB::table('stock_balances')->insert([
        'tenant_id' => $this->tenant->id,
        'outlet_id' => $outlet->id,
        'item_id' => $this->garbageItem->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->artisan("wipro:cleanup-garbage {$this->tenant->id} --force")
        ->expectsOutputToContain('dilewati')
        ->assertExitCode(0);

    expect(Item::query()->whereKey($this->garbageItem->id)->exists())->toBeTrue()
        ->and(ItemCategory::query()->whereKey($this->garbageCategory->id)->exists())->toBeTrue();
});
