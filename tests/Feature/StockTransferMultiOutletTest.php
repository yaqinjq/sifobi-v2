<?php

use App\Models\User;
use App\Modules\Core\Models\Outlet;
use App\Modules\Core\Models\Tenant;
use App\Modules\Inventory\Models\Item;
use App\Modules\Stock\Models\StockTransfer;
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
});

/**
 * @return array<string, mixed>
 */
function stockTransferPayloadFor(object $test, int $fromOutletId, int $toOutletId): array
{
    return [
        'from_outlet_id' => $fromOutletId,
        'to_outlet_id' => $toOutletId,
        'transfer_date' => now()->toDateString(),
        'items' => [[
            'item_id' => $test->item->id,
            'qty' => '1',
        ]],
    ];
}

test('a single-outlet user still has the dropdown pre-scoped to their own outlet, store still works', function (): void {
    $user = User::factory()->create(['tenant_id' => $this->tenant->id, 'outlet_id' => $this->outlet1->id, 'status' => 'ACTIVE']);
    $user->assignRole('PIC_OUTLET');

    $response = $this->actingAs($user)->get(route('operations.stock-transfers.create'));
    $response->assertOk();
    expect($response->viewData('outlets')->pluck('id')->all())->toBe([$this->outlet1->id]);
});

test('a multi-outlet user can create a transfer between 2 outlets they both own', function (): void {
    $user = User::factory()->create(['tenant_id' => $this->tenant->id, 'outlet_id' => $this->outlet1->id, 'status' => 'ACTIVE']);
    $user->assignRole('PIC_OUTLET');
    $user->outlets()->sync([$this->outlet1->id, $this->outlet2->id]);

    $response = $this->actingAs($user)->get(route('operations.stock-transfers.create'));
    $response->assertOk();
    $outletIds = $response->viewData('outlets')->pluck('id')->sort()->values()->all();
    expect($outletIds)->toBe(collect([$this->outlet1->id, $this->outlet2->id])->sort()->values()->all());

    $this->actingAs($user)
        ->post(route('operations.stock-transfers.store'), stockTransferPayloadFor($this, $this->outlet1->id, $this->outlet2->id))
        ->assertRedirect(route('operations.stock-transfers.index'));

    $transfer = StockTransfer::query()->where('tenant_id', $this->tenant->id)->firstOrFail();
    expect($transfer->from_outlet_id)->toBe($this->outlet1->id)
        ->and($transfer->to_outlet_id)->toBe($this->outlet2->id);
});

test('a multi-outlet user cannot transfer to/from an outlet outside their own list', function (): void {
    $user = User::factory()->create(['tenant_id' => $this->tenant->id, 'outlet_id' => $this->outlet1->id, 'status' => 'ACTIVE']);
    $user->assignRole('PIC_OUTLET');

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
        ->post(route('operations.stock-transfers.store'), stockTransferPayloadFor($this, $this->outlet1->id, $this->outlet2->id))
        ->assertSessionHasErrors('to_outlet_id');

    expect(StockTransfer::query()->where('tenant_id', $this->tenant->id)->exists())->toBeFalse();
});

test('a multi-outlet user can view a transfer touching either of their own outlets but not a third', function (): void {
    $owner = User::factory()->create(['tenant_id' => $this->tenant->id, 'outlet_id' => $this->outlet1->id, 'status' => 'ACTIVE']);
    $owner->assignRole('PIC_OUTLET');
    $owner->outlets()->sync([$this->outlet1->id, $this->outlet2->id]);

    $this->actingAs($owner)
        ->post(route('operations.stock-transfers.store'), stockTransferPayloadFor($this, $this->outlet1->id, $this->outlet2->id))
        ->assertRedirect();
    $transfer = StockTransfer::query()->where('tenant_id', $this->tenant->id)->firstOrFail();

    $this->actingAs($owner)->get(route('operations.stock-transfers.show', $transfer))->assertOk();

    $thirdOutlet = Outlet::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenant->id,
        'brand_id' => $this->outlet1->brand_id,
        'legal_entity_id' => $this->outlet1->legal_entity_id,
        'code' => 'MKO_OUTLET_3_'.uniqid(),
        'name' => 'Outlet Ketiga',
        'status' => 'ACTIVE',
    ]);
    $outsider = User::factory()->create(['tenant_id' => $this->tenant->id, 'outlet_id' => $thirdOutlet->id, 'status' => 'ACTIVE']);
    $outsider->assignRole('PIC_OUTLET');

    $this->actingAs($outsider)->get(route('operations.stock-transfers.show', $transfer))->assertForbidden();
});
