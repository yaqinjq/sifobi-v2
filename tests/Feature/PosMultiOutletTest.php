<?php

use App\Models\User;
use App\Modules\Core\Models\Outlet;
use App\Modules\Core\Models\Tenant;
use App\Modules\Pos\Models\PosArea;
use App\Modules\Pos\Models\PosShift;
use App\Modules\Pos\Models\PosTable;
use Database\Seeders\MinimumMasterDataSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Lanjutan akses multi-outlet ke modul POS -- berbeda dari modul lain,
 * sebelum perubahan ini SEMUA endpoint POS (shift/order/area/table) sama
 * sekali TIDAK membatasi outlet_id ke milik user (dropdown selalu
 * menampilkan SEMUA outlet tenant, 'outlet_id' => 'exists:outlets,id'
 * bahkan TANPA syarat tenant_id di PosShiftController::store()) -- jadi
 * perubahan ini juga menutup celah lintas-outlet/lintas-tenant yang
 * sudah ada sebelumnya, bukan cuma menambah dukungan multi-outlet.
 * Modul ini TIDAK ADA test coverage sama sekali sebelum file ini.
 */
beforeEach(function (): void {
    $this->seed([
        RolesAndPermissionsSeeder::class,
        MinimumMasterDataSeeder::class,
    ]);

    $this->tenant = Tenant::query()->where('code', 'MKO')->firstOrFail();
    $this->outlet1 = Outlet::query()->where('tenant_id', $this->tenant->id)->where('code', 'MKO_OUTLET_1')->firstOrFail();
    $this->outlet2 = Outlet::query()->where('tenant_id', $this->tenant->id)->where('code', 'MKO_OUTLET_2')->firstOrFail();
});

function posUser(object $test, bool $multi = false): User
{
    $user = User::factory()->create([
        'tenant_id' => $test->tenant->id,
        'outlet_id' => $test->outlet1->id,
        'status' => 'ACTIVE',
    ]);
    $user->assignRole('PIC_OUTLET');

    if ($multi) {
        $user->outlets()->sync([$test->outlet1->id, $test->outlet2->id]);
    }

    return $user;
}

// ───────────────────────── Shift ─────────────────────────

test('single-outlet user shift dropdown only shows their own outlet, open shift still works', function (): void {
    $user = posUser($this);

    $response = $this->actingAs($user)->get(route('pos.shifts.index'));
    $response->assertOk();
    expect($response->viewData('outlets')->pluck('id')->all())->toBe([$this->outlet1->id]);

    $this->actingAs($user)
        ->post(route('pos.shifts.store'), ['outlet_id' => $this->outlet1->id, 'opening_cash' => '100000'])
        ->assertRedirect();

    expect(PosShift::query()->where('tenant_id', $this->tenant->id)->firstOrFail()->outlet_id)->toBe($this->outlet1->id);
});

test('multi-outlet user can open a shift at either of their own outlets', function (): void {
    $user = posUser($this, multi: true);

    $response = $this->actingAs($user)->get(route('pos.shifts.index'));
    $response->assertOk();
    $outletIds = $response->viewData('outlets')->pluck('id')->sort()->values()->all();
    expect($outletIds)->toBe(collect([$this->outlet1->id, $this->outlet2->id])->sort()->values()->all());

    $this->actingAs($user)
        ->post(route('pos.shifts.store'), ['outlet_id' => $this->outlet2->id, 'opening_cash' => '100000'])
        ->assertRedirect();

    expect(PosShift::query()->where('tenant_id', $this->tenant->id)->firstOrFail()->outlet_id)->toBe($this->outlet2->id);
});

test('multi-outlet user cannot open a shift at an outlet outside their own list', function (): void {
    $user = posUser($this, multi: true);

    $thirdOutlet = Outlet::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenant->id,
        'brand_id' => $this->outlet1->brand_id,
        'legal_entity_id' => $this->outlet1->legal_entity_id,
        'code' => 'MKO_OUTLET_3_'.uniqid(),
        'name' => 'Outlet Ketiga',
        'status' => 'ACTIVE',
    ]);

    $this->actingAs($user)
        ->post(route('pos.shifts.store'), ['outlet_id' => $thirdOutlet->id, 'opening_cash' => '100000'])
        ->assertSessionHasErrors('outlet_id');

    expect(PosShift::query()->where('tenant_id', $this->tenant->id)->exists())->toBeFalse();
});

// ───────────────────────── Order ─────────────────────────

test('single-outlet user order dropdown only shows their own outlet, open order still works', function (): void {
    $user = posUser($this);

    $response = $this->actingAs($user)->get(route('pos.orders.index'));
    $response->assertOk();
    expect($response->viewData('outlets')->pluck('id')->all())->toBe([$this->outlet1->id]);
});

test('multi-outlet user can open an order at either of their own outlets', function (): void {
    $user = posUser($this, multi: true);

    $this->actingAs($user)
        ->post(route('pos.orders.store'), [
            'outlet_id' => $this->outlet2->id,
            'order_type' => \App\Modules\Pos\Models\PosOrder::TYPE_TAKEAWAY,
        ])
        ->assertRedirect();

    expect(\App\Modules\Pos\Models\PosOrder::query()->where('tenant_id', $this->tenant->id)->firstOrFail()->outlet_id)->toBe($this->outlet2->id);
});

test('multi-outlet user cannot open an order at an outlet outside their own list', function (): void {
    $user = posUser($this, multi: true);

    $thirdOutlet = Outlet::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenant->id,
        'brand_id' => $this->outlet1->brand_id,
        'legal_entity_id' => $this->outlet1->legal_entity_id,
        'code' => 'MKO_OUTLET_3_'.uniqid(),
        'name' => 'Outlet Ketiga',
        'status' => 'ACTIVE',
    ]);

    $this->actingAs($user)
        ->post(route('pos.orders.store'), [
            'outlet_id' => $thirdOutlet->id,
            'order_type' => \App\Modules\Pos\Models\PosOrder::TYPE_TAKEAWAY,
        ])
        ->assertSessionHasErrors('outlet_id');
});

// ───────────────────────── Layout (Area & Table) ─────────────────────────

test('multi-outlet user can create a layout area at either of their own outlets but not a third', function (): void {
    $user = posUser($this, multi: true);

    $this->actingAs($user)
        ->post(route('pos.areas.store'), ['outlet_id' => $this->outlet2->id, 'name' => 'Area VIP'])
        ->assertRedirect();
    expect(PosArea::query()->where('tenant_id', $this->tenant->id)->firstOrFail()->outlet_id)->toBe($this->outlet2->id);

    $thirdOutlet = Outlet::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenant->id,
        'brand_id' => $this->outlet1->brand_id,
        'legal_entity_id' => $this->outlet1->legal_entity_id,
        'code' => 'MKO_OUTLET_3_'.uniqid(),
        'name' => 'Outlet Ketiga',
        'status' => 'ACTIVE',
    ]);

    $this->actingAs($user)
        ->post(route('pos.areas.store'), ['outlet_id' => $thirdOutlet->id, 'name' => 'Area Terlarang'])
        ->assertSessionHasErrors('outlet_id');
});

test('multi-outlet user can create a table at either of their own outlets but not a third', function (): void {
    $user = posUser($this, multi: true);

    $area = PosArea::query()->create(['tenant_id' => $this->tenant->id, 'outlet_id' => $this->outlet2->id, 'name' => 'Area Utama', 'sort_order' => 0]);

    $this->actingAs($user)
        ->post(route('pos.tables.store'), ['outlet_id' => $this->outlet2->id, 'pos_area_id' => $area->id, 'code' => 'T-99', 'shape' => PosTable::SHAPE_SQUARE])
        ->assertRedirect();
    expect(PosTable::query()->where('tenant_id', $this->tenant->id)->firstOrFail()->outlet_id)->toBe($this->outlet2->id);

    $thirdOutlet = Outlet::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenant->id,
        'brand_id' => $this->outlet1->brand_id,
        'legal_entity_id' => $this->outlet1->legal_entity_id,
        'code' => 'MKO_OUTLET_3_'.uniqid(),
        'name' => 'Outlet Ketiga',
        'status' => 'ACTIVE',
    ]);

    $this->actingAs($user)
        ->post(route('pos.tables.store'), ['outlet_id' => $thirdOutlet->id, 'pos_area_id' => $area->id, 'code' => 'T-98', 'shape' => PosTable::SHAPE_SQUARE])
        ->assertSessionHasErrors('outlet_id');
});
