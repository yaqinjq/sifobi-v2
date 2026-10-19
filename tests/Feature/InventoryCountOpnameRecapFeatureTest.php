<?php

use App\Models\User;
use App\Modules\Core\Models\Department;
use App\Modules\Core\Models\Outlet;
use App\Modules\Core\Models\Tenant;
use App\Modules\Inventory\Models\Item;
use App\Modules\Operations\Models\OpnameSession;
use App\Services\OpnameService;
use Database\Seeders\MinimumMasterDataSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed([
        RolesAndPermissionsSeeder::class,
        MinimumMasterDataSeeder::class,
    ]);

    config(['app.inventory_simple_enabled' => true]);

    $this->tenant = Tenant::query()->where('code', 'MKO')->firstOrFail();
    $this->outlet = Outlet::query()->where('code', 'MKO_OUTLET_1')->firstOrFail();
    $this->bar = Department::query()->where('code', 'BAR')->firstOrFail();
    $this->kitchen = Department::query()->where('code', 'KITCHEN')->firstOrFail();

    $this->barItem = Item::query()->where('canonical_sku', 'MKO-AJINOMOTO-500GR')->firstOrFail();
    $this->barItem->update(['primary_department_id' => $this->bar->id]);
    Item::query()->where('canonical_sku', 'MKO-GULA-PASIR')->update(['primary_department_id' => $this->kitchen->id]);
});

/**
 * Buat sesi opname, isi item, lalu dorong statusnya ke SUBMITTED langsung
 * lewat model (bukan lewat OpnameService::submit() -- test ini murni
 * menguji halaman rekap read-only, bukan alur submit itu sendiri).
 */
function submittedOpnameSessionForRecap(OpnameSession $session, int $itemId, string $physicalQtyBase): OpnameSession
{
    $session->items()->where('item_id', $itemId)->update([
        'physical_qty_base' => $physicalQtyBase,
        'physical_qty_whole' => $physicalQtyBase,
        'is_counted' => true,
    ]);
    $session->update(['status' => OpnameSession::STATUS_SUBMITTED]);

    return $session->fresh();
}

test('staff sees their submitted opname count in the weekly grid for the right day', function (): void {
    $staff = opnameUser('STAFF_BAR', 'BAR');

    $session = app(OpnameService::class)->startSession([
        'tenant_id' => $this->tenant->id,
        'outlet_id' => $this->outlet->id,
        'department_id' => $this->bar->id,
        'type' => OpnameSession::TYPE_DAILY,
        'opname_date' => now()->toDateString(),
    ], $staff->id);

    submittedOpnameSessionForRecap($session, $this->barItem->id, '42');

    $response = $this->actingAs($staff)->get(route('operations.inventory-count.opname-recap'));

    $response->assertOk();
    $response->assertSee($this->barItem->name);
    $response->assertSee('42');
});

test('draft sessions are NOT treated as final -- the day still shows as not counted', function (): void {
    $staff = opnameUser('STAFF_BAR', 'BAR');

    app(OpnameService::class)->startSession([
        'tenant_id' => $this->tenant->id,
        'outlet_id' => $this->outlet->id,
        'department_id' => $this->bar->id,
        'type' => OpnameSession::TYPE_DAILY,
        'opname_date' => now()->toDateString(),
    ], $staff->id);
    // Sengaja TIDAK di-submit -- tetap DRAFT.

    $response = $this->actingAs($staff)->get(route('operations.inventory-count.opname-recap'));

    $response->assertOk();
    $response->assertSee('Belum dihitung');
});

test('super admin must pick outlet and department before the page loads', function (): void {
    $admin = User::query()->where('email', 'admin@sifobi.test')->firstOrFail();

    $response = $this->actingAs($admin)->get(route('operations.inventory-count.opname-recap'));

    $response->assertForbidden();
});

test('bar staff never sees a kitchen item in this grid', function (): void {
    $barStaff = opnameUser('STAFF_BAR', 'BAR');

    $response = $this->actingAs($barStaff)->get(route('operations.inventory-count.opname-recap'));

    $response->assertOk();
    $response->assertDontSee('Gula Pasir');
});
