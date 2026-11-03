<?php

use App\Models\User;
use App\Modules\Core\Models\Tenant;
use App\Modules\Inventory\Models\Item;
use App\Modules\Inventory\Models\ItemCategory;
use App\Modules\Inventory\Models\Unit;
use Database\Seeders\MinimumMasterDataSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed([
        RolesAndPermissionsSeeder::class,
        MinimumMasterDataSeeder::class,
    ]);

    $this->tenant = Tenant::query()->where('code', 'MKO')->firstOrFail();
    $this->user = User::query()->where('email', 'admin@sifobi.test')->firstOrFail();

    $category = ItemCategory::query()->create([
        'tenant_id' => $this->tenant->id,
        'code'      => 'CAT-BROWSE-TEST',
        'name'      => 'Kategori Tes',
        'status'    => 'ACTIVE',
        'is_active' => true,
    ]);

    $unit = Unit::query()->where('tenant_id', $this->tenant->id)->where('code', 'KG')->first()
        ?? Unit::query()->create(['tenant_id' => $this->tenant->id, 'code' => 'KG', 'name' => 'KG', 'abbreviation' => 'kg']);

    $this->wiproItem = Item::query()->create([
        'tenant_id'         => $this->tenant->id,
        'item_category_id'  => $category->id,
        'name'              => 'Item Wipro Browse Test',
        'canonical_sku'     => 'WIPRO-BROWSE-001',
        'item_source'       => 'WIPRO',
        'item_type'         => 'WIP_L1',
        'inventory_unit_id' => $unit->id,
        'purchase_unit_id'  => $unit->id,
        'base_unit_id'      => $unit->id,
        'is_active'         => true,
    ]);

    $this->ociaItem = Item::query()->create([
        'tenant_id'         => $this->tenant->id,
        'item_category_id'  => $category->id,
        'name'              => 'Item OCIA Browse Test',
        'canonical_sku'     => 'OCIA-BROWSE-001',
        'item_source'       => 'OCIA',
        'ocia_product_id'   => 999,
        'item_type'         => 'BAHAN_BAKU',
        'inventory_unit_id' => $unit->id,
        'purchase_unit_id'  => $unit->id,
        'base_unit_id'      => $unit->id,
        'is_active'         => true,
    ]);
});

test('po_type CENTRAL_KITCHEN hanya menampilkan item Wipro', function (): void {
    $response = $this->actingAs($this->user)->getJson(route('api.items.po-catalog', ['po_type' => 'CENTRAL_KITCHEN']));

    $response->assertOk();
    $names = collect($response->json())->flatten(1)->pluck('name');

    expect($names)->toContain('Item Wipro Browse Test')
        ->and($names)->not->toContain('Item OCIA Browse Test');
});

test('po_type OCIA_ROASTERY hanya menampilkan item OCIA', function (): void {
    $response = $this->actingAs($this->user)->getJson(route('api.items.po-catalog', ['po_type' => 'OCIA_ROASTERY']));

    $response->assertOk();
    $names = collect($response->json())->flatten(1)->pluck('name');

    expect($names)->toContain('Item OCIA Browse Test')
        ->and($names)->not->toContain('Item Wipro Browse Test');
});

test('po_type tanpa sumber katalog (DRYGOOD) mengembalikan kosong', function (): void {
    $response = $this->actingAs($this->user)->getJson(route('api.items.po-catalog', ['po_type' => 'DRYGOOD']));

    $response->assertOk()->assertExactJson([]);
});
