<?php

use App\Models\User;
use App\Modules\Core\Models\Brand;
use App\Modules\Core\Models\Department;
use App\Modules\Core\Models\IntegrationProfile;
use App\Modules\Core\Models\LegalEntity;
use App\Modules\Core\Models\Outlet;
use App\Modules\Core\Models\Tenant;
use App\Modules\Inventory\Models\Item;
use App\Modules\Inventory\Models\ItemBrandAlias;
use App\Modules\Inventory\Models\ItemCategory;
use App\Modules\Inventory\Models\ItemJenis;
use App\Modules\Inventory\Models\Unit;
use App\Modules\Inventory\Models\UnitConversion;
use Database\Seeders\MinimumMasterDataSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed([
        RolesAndPermissionsSeeder::class,
        MinimumMasterDataSeeder::class,
    ]);

    $this->tenant = Tenant::query()->where('code', 'MKO')->firstOrFail();
    $this->admin = User::query()->where('email', 'admin@sifobi.test')->firstOrFail();
});

function masterCrudUser(string $roleName): User
{
    $tenant = Tenant::query()->where('code', 'MKO')->firstOrFail();

    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'name' => $roleName.' User',
        'email' => strtolower($roleName).'_master@sifobi.test',
        'status' => 'ACTIVE',
    ]);

    $user->assignRole($roleName);

    return $user;
}

test('user with view master data can open units index but cannot create unit', function (): void {
    $user = masterCrudUser('STAFF_BAR');

    $this->actingAs($user)
        ->get(route('master-data.units.index'))
        ->assertOk();

    $this->actingAs($user)
        ->get(route('master-data.units.create'))
        ->assertForbidden();
});

test('admin can create update and delete unused unit', function (): void {
    $this->actingAs($this->admin)
        ->post(route('master-data.units.store'), [
            'code' => 'SACHET',
            'name' => 'Sachet',
            'abbreviation' => 'sachet',
        ])
        ->assertRedirect(route('master-data.units.index'));

    $unit = Unit::query()->where('tenant_id', $this->tenant->id)->where('code', 'SACHET')->firstOrFail();

    $this->actingAs($this->admin)
        ->put(route('master-data.units.update', $unit), [
            'code' => 'SACHET',
            'name' => 'Sachet Updated',
            'abbreviation' => 'sct',
        ])
        ->assertRedirect(route('master-data.units.index'));

    $this->assertDatabaseHas('units', [
        'id' => $unit->id,
        'name' => 'Sachet Updated',
        'abbreviation' => 'sct',
    ]);

    $this->actingAs($this->admin)
        ->delete(route('master-data.units.destroy', $unit))
        ->assertRedirect(route('master-data.units.index'));

    $this->assertDatabaseMissing('units', ['id' => $unit->id]);
});

test('unit cannot be deleted when used by items', function (): void {
    $unit = Unit::query()->where('tenant_id', $this->tenant->id)->where('code', 'GR')->firstOrFail();

    $this->actingAs($this->admin)
        ->delete(route('master-data.units.destroy', $unit))
        ->assertSessionHas('error');

    $this->assertDatabaseHas('units', ['id' => $unit->id]);
});

test('admin can create item with ratios and toggle active status', function (): void {
    Storage::fake('public');

    $gr = Unit::query()->where('tenant_id', $this->tenant->id)->where('code', 'GR')->firstOrFail();
    $pack = Unit::query()->where('tenant_id', $this->tenant->id)->where('code', 'PACK')->firstOrFail();
    $bar = Department::query()->where('tenant_id', $this->tenant->id)->where('code', 'BAR')->firstOrFail();
    $kitchen = Department::query()->where('tenant_id', $this->tenant->id)->where('code', 'KITCHEN')->firstOrFail();
    $jenis = ItemJenis::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->where('code', 'RAW_MATERIAL')->firstOrFail();
    $category = ItemCategory::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->where('code', 'POWDER')->firstOrFail();
    $outlets = Outlet::query()->where('tenant_id', $this->tenant->id)->orderBy('id')->take(2)->get();

    $this->actingAs($this->admin)
        ->post(route('master-data.items.store'), [
            'canonical_sku' => 'BCF-TEST-001',
            'name' => 'Bahan Test CRUD',
            'description' => 'Dari test CRUD',
            'keterangan_pembeda' => 'Kemasan test',
            'item_type' => 'BAHAN_BAKU',
            'item_jenis_id' => $jenis->id,
            'item_category_id' => $category->id,
            'base_unit_id' => $gr->id,
            'inventory_unit_id' => $pack->id,
            'inventory_ratio' => '500',
            'purchase_unit_id' => $pack->id,
            'purchase_ratio' => '1000',
            'yield_pct' => '95',
            'opname_frequency' => 'DAILY',
            'primary_department_id' => $bar->id,
            'track_expiry' => '1',
            'department_ids' => [$bar->id, $kitchen->id],
            'outlet_ids' => $outlets->pluck('id')->all(),
            'last_purchase_price' => '25000',
            'is_active' => '1',
            'photo' => UploadedFile::fake()->image('item.jpg', 300, 300)->size(512),
        ])
        ->assertRedirect();

    $item = Item::query()->where('tenant_id', $this->tenant->id)->where('canonical_sku', 'BCF-TEST-001')->firstOrFail();

    expect((string) $item->inventory_ratio)->toBe('500.000000')
        ->and((string) $item->purchase_ratio)->toBe('1000.000000')
        ->and($item->item_jenis_id)->toBe($jenis->id)
        ->and($item->item_category_id)->toBe($category->id)
        ->and($item->keterangan_pembeda)->toBe('Kemasan test')
        ->and($item->opname_frequency)->toBe('DAILY')
        ->and($item->primary_department_id)->toBe($bar->id)
        ->and($item->track_expiry)->toBeTrue()
        ->and($item->photo)->not->toBeNull()
        ->and($item->is_active)->toBeTrue();

    Storage::disk('public')->assertExists($item->photo);
    $this->assertDatabaseHas('item_departments', [
        'item_id' => $item->id,
        'department_id' => $bar->id,
    ]);
    $this->assertDatabaseHas('item_outlets', [
        'tenant_id' => $this->tenant->id,
        'item_id' => $item->id,
        'outlet_id' => $outlets->first()->id,
        'status' => 'ACTIVE',
    ]);

    $this->actingAs($this->admin)
        ->patch(route('master-data.items.toggle-active', $item))
        ->assertRedirect();

    expect($item->refresh()->is_active)->toBeFalse();
});

test('settings pages manage dynamic item jenis categories and departments', function (): void {
    $this->actingAs($this->admin)
        ->get(route('settings.index'))
        ->assertOk()
        ->assertSee('Pengaturan Sistem');

    $this->actingAs($this->admin)
        ->get(route('settings.item-jenises.index'))
        ->assertOk()
        ->assertSee('Dry Good')
        ->assertSee('Raw Material');

    $this->actingAs($this->admin)
        ->post(route('settings.item-jenises.store'), [
            'code' => 'TEST_JENIS',
            'name' => 'Test Jenis',
            'color' => 'indigo',
            'sort_order' => 99,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('item_jenises', [
        'tenant_id' => $this->tenant->id,
        'code' => 'TEST_JENIS',
        'name' => 'Test Jenis',
        'color' => 'indigo',
    ]);

    $this->actingAs($this->admin)
        ->get(route('settings.item-categories.index'))
        ->assertOk()
        ->assertSee('Coffee')
        ->assertSee('Milk');

    $this->actingAs($this->admin)
        ->post(route('settings.item-categories.store'), [
            'code' => 'TEST_CATEGORY',
            'name' => 'Test Category',
            'description' => 'Kategori dari test',
            'sort_order' => 99,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('item_categories', [
        'tenant_id' => $this->tenant->id,
        'code' => 'TEST_CATEGORY',
        'name' => 'Test Category',
        'is_active' => true,
    ]);

    $this->actingAs($this->admin)
        ->get(route('settings.departments.index'))
        ->assertOk()
        ->assertSee('BAR')
        ->assertSee('KITCHEN')
        ->assertSee('OFFICE')
        ->assertSee('PASTRY');

    $this->assertDatabaseMissing('departments', [
        'tenant_id' => $this->tenant->id,
        'code' => 'GUDANG',
        'status' => 'ACTIVE',
    ]);
});

test('admin can manage brands and outlets from settings', function (): void {
    $this->actingAs($this->admin)
        ->get(route('settings.brands.index'))
        ->assertOk()
        ->assertSee('MyKopio');

    $this->actingAs($this->admin)
        ->post(route('settings.brands.store'), [
            'code' => 'TESTBRAND',
            'name' => 'Test Brand',
            'description' => 'Brand dari test',
            'status' => 'ACTIVE',
        ])
        ->assertRedirect(route('settings.brands.index'));

    $brand = Brand::withoutGlobalScopes()
        ->where('tenant_id', $this->tenant->id)
        ->where('code', 'TESTBRAND')
        ->firstOrFail();
    $legalEntity = LegalEntity::query()
        ->where('tenant_id', $this->tenant->id)
        ->where('status', 'ACTIVE')
        ->firstOrFail();

    $this->actingAs($this->admin)
        ->post(route('settings.outlets.store'), [
            'brand_id' => $brand->id,
            'legal_entity_id' => $legalEntity->id,
            'code' => 'TST_OUTLET',
            'name' => 'Test Outlet',
            'address' => 'Jalan Test',
            'contact_phone' => '08123456789',
            'outlet_type' => 'OUTLET',
            'timezone' => 'Asia/Jakarta',
            'status' => 'ACTIVE',
        ])
        ->assertRedirect(route('settings.outlets.index'));

    $this->assertDatabaseHas('outlets', [
        'tenant_id' => $this->tenant->id,
        'brand_id' => $brand->id,
        'code' => 'TST_OUTLET',
        'name' => 'Test Outlet',
        'contact_phone' => '08123456789',
    ]);
});

test('omeo integration sync imports only new outlets', function (): void {
    Http::fake([
        'https://omeo.test/api/outlets' => Http::response([
            'data' => [
                ['kode_outlet' => 'OMEO01', 'nama_outlet' => 'OMEO Outlet 1', 'alamat' => 'Jalan Sync'],
                ['kode_outlet' => 'MKO_OUTLET_1', 'nama_outlet' => 'Existing Outlet'],
            ],
        ], 200),
    ]);

    $this->actingAs($this->admin)
        ->post(route('settings.integrations.store'), [
            'name' => 'OMEO Test',
            'provider' => 'OMEO',
            'base_url' => 'https://omeo.test',
            'auth_type' => 'NONE',
            'outlet_sync_path' => '/api/outlets',
            'health_path' => '/',
            'is_active' => '1',
        ])
        ->assertRedirect();

    $profile = IntegrationProfile::withoutGlobalScopes()
        ->where('tenant_id', $this->tenant->id)
        ->where('name', 'OMEO Test')
        ->firstOrFail();

    $this->actingAs($this->admin)
        ->postJson(route('settings.integrations.sync-outlets', $profile))
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('inserted', 1)
        ->assertJsonPath('skipped', 1);

    $this->assertDatabaseHas('outlets', [
        'tenant_id' => $this->tenant->id,
        'code' => 'OMEO01',
        'name' => 'OMEO Outlet 1',
        'address' => 'Jalan Sync',
    ]);
});

test('user without manage items cannot create item', function (): void {
    $user = masterCrudUser('FINANCE_STAFF');

    $this->actingAs($user)
        ->get(route('master-data.items.create'))
        ->assertForbidden();
});

test('item conversion can be added and removed through ajax routes', function (): void {
    $item = Item::query()->where('tenant_id', $this->tenant->id)->where('canonical_sku', 'MKO-AJINOMOTO-500GR')->firstOrFail();
    $pack = Unit::query()->where('tenant_id', $this->tenant->id)->where('code', 'PACK')->firstOrFail();
    $gr = Unit::query()->where('tenant_id', $this->tenant->id)->where('code', 'GR')->firstOrFail();

    $response = $this->actingAs($this->admin)
        ->postJson(route('master-data.items.conversions.store', $item), [
            'from_unit_id' => $pack->id,
            'to_unit_id' => $gr->id,
            'factor' => '500',
        ])
        ->assertOk()
        ->assertJsonPath('success', true);

    $conversionId = $response->json('conversion.id');

    $this->assertDatabaseHas('unit_conversions', [
        'id' => $conversionId,
        'tenant_id' => $this->tenant->id,
        'item_id' => $item->id,
        'from_unit_id' => $pack->id,
        'to_unit_id' => $gr->id,
        'factor' => '500.00000000',
    ]);

    $conversion = UnitConversion::query()->findOrFail($conversionId);

    $this->actingAs($this->admin)
        ->deleteJson(route('master-data.items.conversions.destroy', [$item, $conversion]))
        ->assertOk()
        ->assertJsonPath('success', true);

    $this->assertDatabaseMissing('unit_conversions', ['id' => $conversionId]);
});

test('item brand alias can be added and removed through ajax routes', function (): void {
    $item = Item::query()->where('tenant_id', $this->tenant->id)->where('canonical_sku', 'MKO-AJINOMOTO-500GR')->firstOrFail();
    $brand = Brand::query()->where('tenant_id', $this->tenant->id)->where('code', 'QUALI')->firstOrFail();

    $response = $this->actingAs($this->admin)
        ->postJson(route('master-data.items.aliases.store', $item), [
            'brand_id' => $brand->id,
            'brand_sku' => 'QL-MSG-500',
            'brand_item_name' => 'MSG 500gr',
            'is_primary' => true,
        ])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('alias.brand_sku', 'QL-MSG-500')
        ->assertJsonPath('alias.is_primary', true);

    $aliasId = $response->json('alias.id');

    $this->assertDatabaseHas('item_brand_aliases', [
        'id' => $aliasId,
        'tenant_id' => $this->tenant->id,
        'item_id' => $item->id,
        'brand_id' => $brand->id,
        'brand_sku' => 'QL-MSG-500',
        'is_primary' => true,
    ]);

    $alias = ItemBrandAlias::withoutGlobalScopes()->findOrFail($aliasId);

    $this->actingAs($this->admin)
        ->deleteJson(route('master-data.items.aliases.destroy', [$item, $alias]))
        ->assertOk()
        ->assertJsonPath('success', true);

    $this->assertDatabaseMissing('item_brand_aliases', ['id' => $aliasId]);
});
