<?php

use App\Models\User;
use App\Modules\Core\Models\Department;
use App\Modules\Core\Models\Outlet;
use App\Modules\Core\Models\Tenant;
use App\Modules\Inventory\Models\Item;
use App\Modules\Operations\Models\SpoilWaste;
use App\Modules\Stock\Models\StockMutation;
use App\Services\StockLedgerService;
use Database\Seeders\MinimumMasterDataSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Lanjutan akses multi-outlet (lihat commit c95ac47 Opname, 9ffad0a
 * infrastruktur) ke modul Spoil & Waste -- SENGAJA tetap fail-closed utk
 * akun multi-outlet sampai mereka pilih 1 outlet aktif secara eksplisit
 * (effectiveSpoilOutletId() di EnforcesSuperAdminScope), BUKAN
 * accessibleOutletIds()/whereIn() permisif seperti Opname/PO/GR -- modul
 * ini tetap kebijakan "tepat 1 outlet per transaksi".
 */
beforeEach(function (): void {
    $this->seed([
        RolesAndPermissionsSeeder::class,
        MinimumMasterDataSeeder::class,
    ]);

    $this->tenant = Tenant::query()->where('code', 'MKO')->firstOrFail();
    $this->outlet1 = Outlet::query()->where('code', 'MKO_OUTLET_1')->firstOrFail();
    $this->outlet2 = Outlet::query()->where('code', 'MKO_OUTLET_2')->firstOrFail();
    $this->bar = Department::query()->where('code', 'BAR')->firstOrFail();

    $this->item = Item::query()->where('canonical_sku', 'MKO-AJINOMOTO-500GR')->firstOrFail();
    $this->item->update(['primary_department_id' => $this->bar->id]);

    foreach ([$this->outlet1, $this->outlet2] as $outlet) {
        app(StockLedgerService::class)->openStock([
            'tenant_id' => $this->tenant->id,
            'outlet_id' => $outlet->id,
            'item_id' => $this->item->id,
            'unit_id' => $this->item->base_unit_id,
            'stock_target' => StockMutation::TARGET_OUTLET_DAILY,
            'qty' => '100',
            'performed_by' => User::query()->where('email', 'admin@sifobi.test')->value('id'),
        ]);
    }

    $this->multiOutletUser = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'outlet_id' => null,
        'department_id' => $this->bar->id,
        'status' => 'ACTIVE',
    ]);
    $this->multiOutletUser->assignRole('STAFF_BAR');
    $this->multiOutletUser->outlets()->sync([$this->outlet1->id, $this->outlet2->id]);
});

test('multi-outlet user without an active outlet selection is fail-closed, not auto-assigned', function (): void {
    $this->multiOutletUser->refresh();
    expect($this->multiOutletUser->hasMultipleOutlets())->toBeTrue();

    $response = $this->actingAs($this->multiOutletUser)->get(route('operations.spoil-wastes.create'));

    $response->assertRedirect(route('operations.spoil-wastes.select-outlet'));
});

test('multi-outlet user can explicitly select an active outlet from their own list', function (): void {
    $response = $this->actingAs($this->multiOutletUser)
        ->post(route('operations.spoil-wastes.set-outlet'), ['outlet_id' => $this->outlet2->id]);

    $response->assertRedirect(route('operations.spoil-wastes.index'));
    expect(session('spoil_active_outlet_id'))->toBe($this->outlet2->id);

    $this->actingAs($this->multiOutletUser)
        ->get(route('operations.spoil-wastes.create'))
        ->assertOk();
});

test('multi-outlet user cannot select an outlet outside their own accessible list', function (): void {
    $otherOutlet = Outlet::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenant->id,
        'brand_id' => $this->outlet1->brand_id,
        'legal_entity_id' => $this->outlet1->legal_entity_id,
        'code' => 'MKO_OUTLET_3_'.uniqid(),
        'name' => 'Outlet Ketiga',
        'status' => 'ACTIVE',
    ]);

    $this->actingAs($this->multiOutletUser)
        ->post(route('operations.spoil-wastes.set-outlet'), ['outlet_id' => $otherOutlet->id])
        ->assertSessionHasErrors('outlet_id');

    expect(session('spoil_active_outlet_id'))->toBeNull();
});

test('after selecting an outlet, spoil is recorded against that outlet and listed correctly', function (): void {
    $this->actingAs($this->multiOutletUser)
        ->post(route('operations.spoil-wastes.set-outlet'), ['outlet_id' => $this->outlet2->id]);

    $response = $this->actingAs($this->multiOutletUser)->post(route('operations.spoil-wastes.store'), [
        'outlet_id' => $this->outlet2->id,
        'department_id' => $this->bar->id,
        'items' => [[
            'item_id' => $this->item->id,
            'qty_whole' => '2',
            'qty_loose' => '0',
            'reason_category' => SpoilWaste::REASON_RUSAK,
        ]],
        'recorded_date' => now()->toDateString(),
    ]);

    $response->assertRedirect();

    $spoil = SpoilWaste::query()->where('tenant_id', $this->tenant->id)->latest('id')->first();
    expect($spoil->outlet_id)->toBe($this->outlet2->id);

    $this->actingAs($this->multiOutletUser)
        ->get(route('operations.spoil-wastes.show', $spoil))
        ->assertOk();
});

test('switching active outlet changes which spoil records are visible', function (): void {
    $this->actingAs($this->multiOutletUser)
        ->post(route('operations.spoil-wastes.set-outlet'), ['outlet_id' => $this->outlet1->id]);
    $responseOutlet1 = $this->actingAs($this->multiOutletUser)->post(route('operations.spoil-wastes.store'), [
        'outlet_id' => $this->outlet1->id,
        'department_id' => $this->bar->id,
        'items' => [['item_id' => $this->item->id, 'qty_whole' => '1', 'qty_loose' => '0', 'reason_category' => SpoilWaste::REASON_RUSAK]],
        'recorded_date' => now()->toDateString(),
    ]);
    $responseOutlet1->assertRedirect();
    $spoilOutlet1 = SpoilWaste::query()->where('outlet_id', $this->outlet1->id)->latest('id')->firstOrFail();

    $this->actingAs($this->multiOutletUser)
        ->post(route('operations.spoil-wastes.set-outlet'), ['outlet_id' => $this->outlet2->id]);

    // Sesi lama di outlet1 sekarang di LUAR scope aktif (outlet2) --
    // harus 403, bukan diam-diam tetap bisa diakses.
    $this->actingAs($this->multiOutletUser)
        ->get(route('operations.spoil-wastes.show', $spoilOutlet1))
        ->assertForbidden();
});

test('single-outlet user is completely unaffected: no redirect, no select-outlet step', function (): void {
    $singleOutletUser = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'outlet_id' => $this->outlet1->id,
        'department_id' => $this->bar->id,
        'status' => 'ACTIVE',
    ]);
    $singleOutletUser->assignRole('STAFF_BAR');

    $this->actingAs($singleOutletUser)
        ->get(route('operations.spoil-wastes.create'))
        ->assertOk();
});
