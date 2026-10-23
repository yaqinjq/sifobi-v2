<?php

use App\Models\User;
use App\Modules\Core\Models\Outlet;
use App\Modules\Core\Models\Tenant;
use App\Modules\Inventory\Models\Item;
use App\Modules\Inventory\Models\Unit;
use App\Modules\Procurement\Models\PurchaseOrder;
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
    $this->outlet1 = Outlet::query()->where('tenant_id', $this->tenant->id)->where('code', 'MKO_OUTLET_1')->firstOrFail();
    $this->outlet2 = Outlet::query()->where('tenant_id', $this->tenant->id)->where('code', 'MKO_OUTLET_2')->firstOrFail();
    $this->item = Item::query()->where('tenant_id', $this->tenant->id)->where('canonical_sku', 'MKO-AJINOMOTO-500GR')->firstOrFail();
    $this->unit = Unit::query()->where('tenant_id', $this->tenant->id)->where('id', $this->item->base_unit_id)->firstOrFail();
});

function poTabsPayload(object $test): array
{
    return [
        'CENTRAL_KITCHEN' => [
            ['item_id' => $test->item->id, 'unit_id' => $test->unit->id, 'qty_ordered' => 5],
        ],
    ];
}

test('a single-outlet user can still create a PO with zero UI change -- no outlet_id needed, resolved automatically', function (): void {
    $user = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'outlet_id' => $this->outlet1->id,
        'status' => 'ACTIVE',
    ]);
    $user->assignRole('PIC_OUTLET');

    $response = $this->actingAs($user)->postJson(route('procurement.purchase-orders.store-batch'), [
        'needed_at' => now()->addDay()->toDateString(),
        'tabs' => poTabsPayload($this),
    ]);

    $response->assertOk();

    $po = PurchaseOrder::query()->where('tenant_id', $this->tenant->id)->firstOrFail();
    expect($po->outlet_id)->toBe($this->outlet1->id);
});

test('a user with zero accessible outlets still gets the original blocking message', function (): void {
    $user = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'outlet_id' => null,
        'status' => 'ACTIVE',
    ]);
    $user->assignRole('PIC_OUTLET');

    $response = $this->actingAs($user)->postJson(route('procurement.purchase-orders.store-batch'), [
        'needed_at' => now()->addDay()->toDateString(),
        'tabs' => poTabsPayload($this),
    ]);

    $response->assertStatus(422)
        ->assertJsonFragment(['error' => 'Akun Anda belum ditugaskan ke outlet manapun, jadi Purchase Order tidak bisa dibuat. Hubungi admin untuk mengatur outlet di menu Manajemen User.']);
});

test('a multi-outlet user must pick an outlet -- storeBatch rejects when outlet_id is missing', function (): void {
    $user = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'outlet_id' => $this->outlet1->id,
        'status' => 'ACTIVE',
    ]);
    $user->assignRole('PIC_OUTLET');
    $user->outlets()->sync([$this->outlet1->id, $this->outlet2->id]);

    $response = $this->actingAs($user)->postJson(route('procurement.purchase-orders.store-batch'), [
        'needed_at' => now()->addDay()->toDateString(),
        'tabs' => poTabsPayload($this),
    ]);

    $response->assertStatus(422)->assertJsonValidationErrors('outlet_id');
});

test('a multi-outlet user can create a PO for any outlet in their own list', function (): void {
    $user = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'outlet_id' => $this->outlet1->id,
        'status' => 'ACTIVE',
    ]);
    $user->assignRole('PIC_OUTLET');
    $user->outlets()->sync([$this->outlet1->id, $this->outlet2->id]);

    $response = $this->actingAs($user)->postJson(route('procurement.purchase-orders.store-batch'), [
        'needed_at' => now()->addDay()->toDateString(),
        'tabs' => poTabsPayload($this),
        'outlet_id' => $this->outlet2->id,
    ]);

    $response->assertOk();

    $po = PurchaseOrder::query()->where('tenant_id', $this->tenant->id)->firstOrFail();
    expect($po->outlet_id)->toBe($this->outlet2->id);
});

test('a multi-outlet user cannot create a PO for an outlet outside their own list -- authorization test', function (): void {
    $user = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'outlet_id' => $this->outlet1->id,
        'status' => 'ACTIVE',
    ]);
    $user->assignRole('PIC_OUTLET');

    // User cuma dapat akses outlet1 + outlet3 (bukan outlet2) -- supaya
    // kondisi multi-outlet beneran kebentuk (hasMultipleOutlets() true)
    // TAPI outlet2 tetap di luar daftarnya.
    $thirdOutlet = Outlet::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenant->id,
        'brand_id' => $this->outlet1->brand_id,
        'legal_entity_id' => $this->outlet1->legal_entity_id,
        'code' => 'MKO_OUTLET_3_'.uniqid(),
        'name' => 'Outlet Ketiga',
        'status' => 'ACTIVE',
    ]);
    $user->outlets()->sync([$this->outlet1->id, $thirdOutlet->id]);

    $response = $this->actingAs($user)->postJson(route('procurement.purchase-orders.store-batch'), [
        'needed_at' => now()->addDay()->toDateString(),
        'tabs' => poTabsPayload($this),
        'outlet_id' => $this->outlet2->id, // di luar daftar user ini
    ]);

    $response->assertStatus(422)->assertJsonValidationErrors('outlet_id');
    expect(PurchaseOrder::query()->where('tenant_id', $this->tenant->id)->exists())->toBeFalse();
});

test('PO index lists orders across all of a multi-outlet users accessible outlets', function (): void {
    $user = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'outlet_id' => $this->outlet1->id,
        'status' => 'ACTIVE',
    ]);
    $user->assignRole('PIC_OUTLET');
    $user->outlets()->sync([$this->outlet1->id, $this->outlet2->id]);

    $this->actingAs($user)->postJson(route('procurement.purchase-orders.store-batch'), [
        'needed_at' => now()->addDay()->toDateString(),
        'tabs' => poTabsPayload($this),
        'outlet_id' => $this->outlet1->id,
    ])->assertOk();

    $this->actingAs($user)->postJson(route('procurement.purchase-orders.store-batch'), [
        'needed_at' => now()->addDay()->toDateString(),
        'tabs' => poTabsPayload($this),
        'outlet_id' => $this->outlet2->id,
    ])->assertOk();

    expect(PurchaseOrder::query()->where('tenant_id', $this->tenant->id)->count())->toBe(2);

    $response = $this->actingAs($user)->get(route('procurement.purchase-orders.index'));
    $response->assertOk();

    $pos = $response->viewData('pos');
    expect($pos->total())->toBe(2);
});
