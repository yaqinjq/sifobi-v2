<?php

use App\Models\User;
use App\Modules\Core\Models\Outlet;
use App\Modules\Core\Models\Tenant;
use App\Modules\Inventory\Models\Item;
use App\Modules\Receiving\Models\GoodsReceipt;
use App\Modules\Receiving\Models\Supplier;
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
    $this->supplier = Supplier::query()->where('tenant_id', $this->tenant->id)->where('code', 'SUP-001')->firstOrFail();
});

/**
 * @return array<string, mixed>
 */
function grPayloadFor(object $test, int $outletId): array
{
    return [
        'source' => GoodsReceipt::SOURCE_SUPPLIER_LUAR,
        'outlet_id' => $outletId,
        'supplier_id' => $test->supplier->id,
        'doc_number' => 'SJ-MULTI-001',
        'invoice_number' => 'INV-MULTI-001',
        'receipt_date' => '2026-07-01',
        'notes' => 'Penerimaan test multi-outlet',
        'items' => [
            [
                'item_id' => $test->item->id,
                'unit_id' => $test->item->purchase_unit_id,
                'qty_ordered' => '2',
                'qty_received' => '2',
                'unit_price' => '25000',
            ],
        ],
    ];
}

test('a single-outlet user still has the dropdown pre-scoped to their own outlet, store works unchanged', function (): void {
    $user = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'outlet_id' => $this->outlet1->id,
        'status' => 'ACTIVE',
    ]);
    $user->assignRole('STAFF_GUDANG');

    $response = $this->actingAs($user)->get(route('receiving.goods-receipts.create'));
    $response->assertOk();
    expect($response->viewData('outlets')->pluck('id')->all())->toBe([$this->outlet1->id]);

    $this->actingAs($user)
        ->post(route('receiving.goods-receipts.store'), grPayloadFor($this, $this->outlet1->id))
        ->assertRedirect();

    expect(GoodsReceipt::query()->where('tenant_id', $this->tenant->id)->firstOrFail()->outlet_id)->toBe($this->outlet1->id);
});

test('a multi-outlet user sees only their own outlets in the dropdown and can create a receipt for any of them', function (): void {
    $user = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'outlet_id' => $this->outlet1->id,
        'status' => 'ACTIVE',
    ]);
    $user->assignRole('STAFF_GUDANG');
    $user->outlets()->sync([$this->outlet1->id, $this->outlet2->id]);

    $response = $this->actingAs($user)->get(route('receiving.goods-receipts.create'));
    $response->assertOk();
    $outletIds = $response->viewData('outlets')->pluck('id')->sort()->values()->all();
    expect($outletIds)->toBe(collect([$this->outlet1->id, $this->outlet2->id])->sort()->values()->all());

    $this->actingAs($user)
        ->post(route('receiving.goods-receipts.store'), grPayloadFor($this, $this->outlet2->id))
        ->assertRedirect();

    expect(GoodsReceipt::query()->where('tenant_id', $this->tenant->id)->firstOrFail()->outlet_id)->toBe($this->outlet2->id);
});

test('a multi-outlet user cannot create a receipt for an outlet outside their own list -- authorization test', function (): void {
    $user = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'outlet_id' => $this->outlet1->id,
        'status' => 'ACTIVE',
    ]);
    $user->assignRole('STAFF_GUDANG');

    $thirdOutlet = Outlet::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenant->id,
        'brand_id' => $this->outlet1->brand_id,
        'legal_entity_id' => $this->outlet1->legal_entity_id,
        'code' => 'MKO_OUTLET_3_'.uniqid(),
        'name' => 'Outlet Ketiga',
        'status' => 'ACTIVE',
    ]);
    $user->outlets()->sync([$this->outlet1->id, $thirdOutlet->id]);

    $this->actingAs($user)
        ->post(route('receiving.goods-receipts.store'), grPayloadFor($this, $this->outlet2->id))
        ->assertSessionHasErrors('outlet_id');

    expect(GoodsReceipt::query()->where('tenant_id', $this->tenant->id)->exists())->toBeFalse();
});

test('a multi-outlet user can view and edit a receipt from any of their own outlets, not just the first', function (): void {
    $user = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'outlet_id' => $this->outlet1->id,
        'status' => 'ACTIVE',
    ]);
    $user->assignRole('STAFF_GUDANG');
    $user->outlets()->sync([$this->outlet1->id, $this->outlet2->id]);

    $this->actingAs($user)
        ->post(route('receiving.goods-receipts.store'), grPayloadFor($this, $this->outlet2->id))
        ->assertRedirect();

    $receipt = GoodsReceipt::query()->where('outlet_id', $this->outlet2->id)->firstOrFail();

    // Sebelum perbaikan ini, authorizeReceiptOutlet() (dan sebelumnya
    // pengecekan outlet_id legacy) akan menolak akses ke receipt
    // outlet2 karena cuma mengecek outlet_id LEGACY (outlet1).
    $this->actingAs($user)
        ->get(route('receiving.goods-receipts.show', $receipt))
        ->assertOk();

    $this->actingAs($user)
        ->get(route('receiving.goods-receipts.edit', $receipt))
        ->assertOk();
});
