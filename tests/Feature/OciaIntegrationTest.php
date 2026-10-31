<?php

use App\Models\User;
use App\Modules\Core\Models\IntegrationProfile;
use App\Modules\Core\Models\Tenant;
use App\Modules\Inventory\Models\Item;
use Database\Seeders\MinimumMasterDataSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed([
        RolesAndPermissionsSeeder::class,
        MinimumMasterDataSeeder::class,
    ]);

    $this->tenant = Tenant::query()->where('code', 'MKO')->firstOrFail();
    $this->admin = User::query()->where('email', 'admin@sifobi.test')->firstOrFail();

    // MinimumMasterDataSeeder SUDAH membuat 1 baris profil OCIA (lihat
    // seeder-nya) -- update baris itu, bukan bikin baru (unique constraint
    // tenant_id+code).
    $this->profile = IntegrationProfile::withoutGlobalScopes()
        ->where('tenant_id', $this->tenant->id)
        ->where('code', 'OCIA')
        ->firstOrFail();
    $this->profile->update([
        'base_url' => 'https://ocia.test',
        'auth_mode' => 'BEARER',
        'auth_type' => 'BEARER',
        'auth_token' => 'test-token',
        'api_token' => 'test-token',
        'is_active' => true,
        'meta' => [
            'health_path' => '/api_v1/health',
            'catalog_path' => '/api/products',
            'po_list_path' => '/api_v1/roastery_orders_available',
            'order_path' => '/api_v1/roastery_orders',
            'timeout_seconds' => 20,
        ],
    ]);
});

/**
 * @param  list<array<string, mixed>>  $records
 */
function fakeOciaCatalog(array $records): void
{
    Http::fake([
        'https://ocia.test/api/products' => Http::response([
            'ok' => true,
            'data' => ['records' => $records, 'total' => count($records)],
        ], 200),
    ]);
}

test('syncing the OCIA catalog creates new items with item_source OCIA', function (): void {
    fakeOciaCatalog([
        [
            'product_id' => 1,
            'product_code' => '1-BL-AR-D-RGL-RB-AA1',
            'product_name' => 'A1',
            'is_active' => true,
            'komposisi' => 'BLENDED',
            'jenis_komposisi' => 'ARABUSTA',
            'unit' => 'kg',
        ],
        [
            'product_id' => 33,
            'product_code' => '33-SO-A-M-RGL-RB-AKNARABI',
            'product_name' => 'ARABICA KAMOJANG NATURAL',
            'is_active' => true,
            'komposisi' => 'SINGLE ORIGIN',
            'jenis_komposisi' => 'ARABICA',
            'unit' => 'kg',
        ],
    ]);

    $response = $this->actingAs($this->admin)
        ->postJson(route('settings.integrations.sync-catalog', $this->profile));

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('inserted', 2)
        ->assertJsonPath('updated', 0);

    $this->assertDatabaseHas('items', [
        'tenant_id' => $this->tenant->id,
        'canonical_sku' => '1-BL-AR-D-RGL-RB-AA1',
        'name' => 'A1',
        'item_source' => 'OCIA',
        'ocia_product_id' => 1,
        'track_stock' => true,
    ]);

    $item = Item::withoutGlobalScopes()->where('canonical_sku', '33-SO-A-M-RGL-RB-AKNARABI')->firstOrFail();
    expect($item->item_source)->toBe('OCIA')
        ->and($item->po_destinations)->toBe(['OCIA_ROASTERY'])
        ->and($item->category?->name)->toBe('OCIA — Single Origin Arabica');

    $this->profile->refresh();
    expect($this->profile->last_synced_at)->not->toBeNull();
});

test('re-syncing the same product updates the existing item instead of creating a duplicate', function (): void {
    // Http::fake() dipanggil 2x dengan URL yang SAMA tidak menimpa respons
    // pertama (stub lama tetap dipakai) -- WAJIB pakai fakeSequence() kalau
    // mau URL yang sama membalas beda di panggilan ke-2 dalam 1 test.
    Http::fakeSequence('https://ocia.test/api/products')
        ->push([
            'ok' => true,
            'data' => ['records' => [[
                'product_id' => 1, 'product_code' => 'CODE-1', 'product_name' => 'Old Name',
                'is_active' => true, 'komposisi' => 'BLENDED', 'jenis_komposisi' => 'ARABUSTA', 'unit' => 'kg',
            ]], 'total' => 1],
        ], 200)
        ->push([
            'ok' => true,
            'data' => ['records' => [[
                'product_id' => 1, 'product_code' => 'CODE-1-RENAMED', 'product_name' => 'New Name',
                'is_active' => false, 'komposisi' => 'BLENDED', 'jenis_komposisi' => 'ARABUSTA', 'unit' => 'kg',
            ]], 'total' => 1],
        ], 200);

    $this->actingAs($this->admin)->postJson(route('settings.integrations.sync-catalog', $this->profile))->assertOk();

    $response = $this->actingAs($this->admin)->postJson(route('settings.integrations.sync-catalog', $this->profile));

    $response->assertOk()->assertJsonPath('inserted', 0)->assertJsonPath('updated', 1);

    expect(Item::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->where('item_source', 'OCIA')->count())->toBe(1);

    $item = Item::withoutGlobalScopes()->where('ocia_product_id', 1)->firstOrFail();
    expect($item->canonical_sku)->toBe('CODE-1-RENAMED')
        ->and($item->name)->toBe('New Name')
        ->and($item->is_active)->toBeFalse();
});

test('a manually customized OCIA item (photo set) keeps its category on re-sync', function (): void {
    fakeOciaCatalog([[
        'product_id' => 1, 'product_code' => 'CODE-1', 'product_name' => 'A1',
        'is_active' => true, 'komposisi' => 'BLENDED', 'jenis_komposisi' => 'ARABUSTA', 'unit' => 'kg',
    ]]);
    $this->actingAs($this->admin)->postJson(route('settings.integrations.sync-catalog', $this->profile))->assertOk();

    $item = Item::withoutGlobalScopes()->where('ocia_product_id', 1)->firstOrFail();
    $customCategory = \App\Modules\Inventory\Models\ItemCategory::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenant->id, 'code' => 'CUSTOM_CAT', 'name' => 'Kategori Kustom Admin',
        'status' => 'ACTIVE', 'is_active' => true,
    ]);
    $item->update(['photo' => 'items/custom-photo.jpg', 'item_category_id' => $customCategory->id]);

    $this->actingAs($this->admin)->postJson(route('settings.integrations.sync-catalog', $this->profile))->assertOk();

    expect($item->refresh()->item_category_id)->toBe($customCategory->id);
});

test('syncing a product code already used by a non-OCIA item is skipped and reported as an error, not overwritten', function (): void {
    $conflicting = Item::withoutGlobalScopes()->where('canonical_sku', 'MKO-AJINOMOTO-500GR')->firstOrFail();

    fakeOciaCatalog([[
        'product_id' => 999, 'product_code' => 'MKO-AJINOMOTO-500GR', 'product_name' => 'Bukan Ajinomoto',
        'is_active' => true, 'komposisi' => 'BLENDED', 'jenis_komposisi' => 'ARABUSTA', 'unit' => 'kg',
    ]]);

    $response = $this->actingAs($this->admin)->postJson(route('settings.integrations.sync-catalog', $this->profile));

    $response->assertOk()->assertJsonPath('inserted', 0)->assertJsonPath('updated', 0);
    expect($response->json('errors'))->toHaveCount(1);

    expect($conflicting->refresh()->name)->toBe('Ajinomoto 500gr')
        ->and($conflicting->item_source)->not->toBe('OCIA');
});

test('sync-catalog is rejected for a non-OCIA integration profile', function (): void {
    $otherProfile = IntegrationProfile::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenant->id, 'code' => 'OMEO', 'provider' => 'OMEO',
        'name' => 'OMEO Test', 'base_url' => 'https://omeo.test', 'auth_mode' => 'NONE', 'is_active' => true,
    ]);

    $this->actingAs($this->admin)
        ->postJson(route('settings.integrations.sync-catalog', $otherProfile))
        ->assertStatus(422)
        ->assertJsonPath('success', false);
});

test('PO item search for OCIA_ROASTERY only returns OCIA-sourced items, never Wipro or generic ones', function (): void {
    fakeOciaCatalog([[
        'product_id' => 1, 'product_code' => 'OCIA-ARABICA', 'product_name' => 'OCIA Arabica Blend',
        'is_active' => true, 'komposisi' => 'BLENDED', 'jenis_komposisi' => 'ARABICA', 'unit' => 'kg',
    ]]);
    $this->actingAs($this->admin)->postJson(route('settings.integrations.sync-catalog', $this->profile))->assertOk();

    // Item non-OCIA yang kebetulan juga cocok kata kuncinya ("Arabica") --
    // satuan dipinjam dari item yang sudah ada di seeder, bukan bagian
    // penting dari skenario ini.
    $genericItem = Item::withoutGlobalScopes()->where('canonical_sku', 'MKO-AJINOMOTO-500GR')->firstOrFail();
    Item::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenant->id,
        'canonical_sku' => 'GENERIC-ARABICA',
        'name' => 'Arabica Generic Non-OCIA',
        'item_type' => 'BAHAN_BAKU',
        'inventory_unit_id' => $genericItem->inventory_unit_id,
        'base_unit_id' => $genericItem->base_unit_id,
        'purchase_unit_id' => $genericItem->purchase_unit_id,
        'is_active' => true,
        'track_stock' => true,
    ]);

    $response = $this->actingAs($this->admin)
        ->getJson(route('api.items.search-for-po', ['q' => 'Arabica', 'po_type' => 'OCIA_ROASTERY']));

    $response->assertOk();
    $names = collect($response->json())->pluck('name')->all();
    expect($names)->toContain('OCIA Arabica Blend')
        ->and($names)->not->toContain('Arabica Generic Non-OCIA');
});
