<?php

use App\Models\User;
use App\Modules\Core\Models\Department;
use App\Modules\Core\Models\Outlet;
use App\Modules\Core\Models\Tenant;
use App\Modules\Inventory\Models\Item;
use App\Modules\Operations\Models\OpenStock;
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
    $this->department = Department::query()->where('tenant_id', $this->tenant->id)->where('code', 'BAR')->firstOrFail();
});

/**
 * @return array<string, mixed>
 */
function openStockPayloadFor(object $test, int $outletId): array
{
    return [
        'outlet_id' => $outletId,
        'stock_target' => OpenStock::TARGET_OUTLET_DAILY,
        'business_date' => '2026-06-28',
        'items' => [[
            'item_id' => $test->item->id,
            'department_id' => $test->department->id,
            'qty_whole' => '2',
            'qty_loose' => '0',
            'cost_per_unit' => '12000',
        ]],
    ];
}

test('a single-outlet user is unaffected: dropdown still shows only their own outlet, store still works', function (): void {
    $user = User::factory()->create(['tenant_id' => $this->tenant->id, 'outlet_id' => $this->outlet1->id, 'status' => 'ACTIVE']);
    $user->assignRole('PIC_OUTLET');

    $response = $this->actingAs($user)->get(route('operations.open-stocks.create'));
    $response->assertOk();
    expect($response->viewData('outlets')->pluck('id')->all())->toBe([$this->outlet1->id]);

    $this->actingAs($user)
        ->postJson(route('operations.open-stocks.store'), openStockPayloadFor($this, $this->outlet1->id))
        ->assertOk();

    expect(OpenStock::query()->where('tenant_id', $this->tenant->id)->firstOrFail()->outlet_id)->toBe($this->outlet1->id);
});

test('a multi-outlet user sees only their own outlets and can create open stock for either one', function (): void {
    $user = User::factory()->create(['tenant_id' => $this->tenant->id, 'outlet_id' => $this->outlet1->id, 'status' => 'ACTIVE']);
    $user->assignRole('PIC_OUTLET');
    $user->outlets()->sync([$this->outlet1->id, $this->outlet2->id]);

    $response = $this->actingAs($user)->get(route('operations.open-stocks.create'));
    $response->assertOk();
    $outletIds = $response->viewData('outlets')->pluck('id')->sort()->values()->all();
    expect($outletIds)->toBe(collect([$this->outlet1->id, $this->outlet2->id])->sort()->values()->all());

    $this->actingAs($user)
        ->postJson(route('operations.open-stocks.store'), openStockPayloadFor($this, $this->outlet2->id))
        ->assertOk();

    expect(OpenStock::query()->where('tenant_id', $this->tenant->id)->firstOrFail()->outlet_id)->toBe($this->outlet2->id);
});

test('a multi-outlet user cannot submit open stock for an outlet outside their own list', function (): void {
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
        ->post(route('operations.open-stocks.store'), openStockPayloadFor($this, $this->outlet2->id))
        ->assertSessionHasErrors('outlet_id');

    expect(OpenStock::query()->where('tenant_id', $this->tenant->id)->exists())->toBeFalse();
});

test('a multi-outlet user can only view/list open stock for outlets they own', function (): void {
    $owner = User::factory()->create(['tenant_id' => $this->tenant->id, 'outlet_id' => $this->outlet1->id, 'status' => 'ACTIVE']);
    $owner->assignRole('PIC_OUTLET');
    $owner->outlets()->sync([$this->outlet1->id, $this->outlet2->id]);

    $this->actingAs($owner)->postJson(route('operations.open-stocks.store'), openStockPayloadFor($this, $this->outlet2->id))->assertOk();
    $created = OpenStock::query()->where('tenant_id', $this->tenant->id)->where('outlet_id', $this->outlet2->id)->firstOrFail();

    $this->actingAs($owner)->get(route('operations.open-stocks.show', $created))->assertOk();

    $outsider = User::factory()->create(['tenant_id' => $this->tenant->id, 'outlet_id' => $this->outlet1->id, 'status' => 'ACTIVE']);
    $outsider->assignRole('PIC_OUTLET');

    $this->actingAs($outsider)->get(route('operations.open-stocks.show', $created))->assertForbidden();
});
