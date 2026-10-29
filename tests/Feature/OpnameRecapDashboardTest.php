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
    $this->barItem = Item::query()->where('canonical_sku', 'MKO-AJINOMOTO-500GR')->firstOrFail();
    $this->barItem->update(['primary_department_id' => $this->bar->id]);
});

function recapDashboardUser(string $roleName, ?string $outletCode = null, ?string $departmentCode = null): User
{
    $tenant = Tenant::query()->where('code', 'MKO')->firstOrFail();
    $outlet = $outletCode ? Outlet::query()->where('code', $outletCode)->firstOrFail() : null;
    $department = $departmentCode ? Department::query()->where('code', $departmentCode)->firstOrFail() : null;

    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'outlet_id' => $outlet?->id,
        'department_id' => $department?->id,
        'status' => 'ACTIVE',
    ]);
    $user->assignRole($roleName);

    return $user;
}

test('GENERAL_FINANCE (no input_opname/approve_opname at all) can reach opname-history and opname-recap', function (): void {
    $finance = recapDashboardUser('GENERAL_FINANCE');

    $this->actingAs($finance)->get(route('operations.inventory-count.opname-history'))->assertOk();

    $this->actingAs($finance)
        ->get(route('operations.inventory-count.opname-recap', ['outlet_id' => $this->outlet->id, 'department_id' => $this->bar->id]))
        ->assertOk();
});

test('GENERAL_FINANCE is still blocked from the rest of the Inventory Simple module (index/start/movements)', function (): void {
    $finance = recapDashboardUser('GENERAL_FINANCE');

    $this->actingAs($finance)->get(route('operations.inventory-count.index'))->assertForbidden();
    $this->actingAs($finance)->post(route('operations.inventory-count.start'), [])->assertForbidden();
});

test('GENERAL_FINANCE sees outlet & department dropdowns and can pick any pair, just like SUPER_ADMIN', function (): void {
    $finance = recapDashboardUser('GENERAL_FINANCE');
    $kitchen = Department::query()->where('code', 'KITCHEN')->firstOrFail();

    $response = $this->actingAs($finance)
        ->get(route('operations.inventory-count.opname-recap', ['outlet_id' => $this->outlet->id, 'department_id' => $kitchen->id]));

    $response->assertOk();
    $response->assertSee($this->outlet->name);
    $response->assertSee($kitchen->name);
});

test('GENERAL_FINANCE without outlet_id/department_id picked yet gets a clear 403, not a crash', function (): void {
    $finance = recapDashboardUser('GENERAL_FINANCE');

    $this->actingAs($finance)->get(route('operations.inventory-count.opname-recap'))->assertForbidden();
});

test('a regular department-scoped staff cannot override their outlet/department via query params on opname-recap', function (): void {
    $staff = recapDashboardUser('STAFF_BAR', 'MKO_OUTLET_1', 'BAR');
    $otherOutlet = Outlet::query()->where('code', 'MKO_OUTLET_2')->firstOrFail();

    $response = $this->actingAs($staff)
        ->get(route('operations.inventory-count.opname-recap', ['outlet_id' => $otherOutlet->id, 'department_id' => $this->bar->id]));

    $response->assertOk();
    // Harus tetap pakai outlet_id/department_id MILIKNYA sendiri, bukan yang
    // dikirim lewat query string -- input client diabaikan total untuk
    // role non-SUPER_ADMIN/GENERAL_FINANCE.
    $response->assertSee($this->outlet->name);
    $response->assertDontSee($otherOutlet->name);
});

test('a custom date_from/date_to range renders exactly that many day columns and places counted data on the right day', function (): void {
    $finance = recapDashboardUser('GENERAL_FINANCE');

    $dateFrom = now()->subDays(9)->startOfDay();
    $dateTo = now()->subDays(3)->startOfDay(); // 7 hari (9..3 mundur = 7 hari inklusif)

    $session = app(OpnameService::class)->startSession([
        'tenant_id' => $this->tenant->id,
        'outlet_id' => $this->outlet->id,
        'department_id' => $this->bar->id,
        'type' => OpnameSession::TYPE_DAILY,
        'opname_date' => $dateFrom->copy()->addDays(2)->toDateString(),
    ], $finance->id);
    $session->items()->where('item_id', $this->barItem->id)->update([
        'physical_qty_base' => '5.25',
        'is_counted' => true,
    ]);
    $session->update(['status' => OpnameSession::STATUS_SUBMITTED]);

    $response = $this->actingAs($finance)->get(route('operations.inventory-count.opname-recap', [
        'outlet_id' => $this->outlet->id,
        'department_id' => $this->bar->id,
        'date_from' => $dateFrom->toDateString(),
        'date_to' => $dateTo->toDateString(),
    ]));

    $response->assertOk();
    $response->assertSee('5.25');

    $html = $response->getContent();
    expect(substr_count($html, 'recap-table'))->toBeGreaterThan(0);
});

test('a date range longer than the cap is clamped instead of erroring out', function (): void {
    $finance = recapDashboardUser('GENERAL_FINANCE');

    $response = $this->actingAs($finance)->get(route('operations.inventory-count.opname-recap', [
        'outlet_id' => $this->outlet->id,
        'department_id' => $this->bar->id,
        'date_from' => now()->subYear()->toDateString(),
        'date_to' => now()->toDateString(),
    ]));

    $response->assertOk();
});

test('without any date param, opname-recap still defaults to the current week (backward compatible)', function (): void {
    $staff = recapDashboardUser('STAFF_BAR', 'MKO_OUTLET_1', 'BAR');

    $this->actingAs($staff)->get(route('operations.inventory-count.opname-recap'))->assertOk();
});

test('GENERAL_FINANCE sees the Rekap Opname entry point on the Laporan menu', function (): void {
    $finance = recapDashboardUser('GENERAL_FINANCE');

    $this->actingAs($finance)
        ->get(route('laporan.index'))
        ->assertOk()
        ->assertSee('Rekap Hasil Hitung Opname');
});

test('picking "Semua Departemen" (no department_id) shows items from every active department, prefixed by department name', function (): void {
    $finance = recapDashboardUser('GENERAL_FINANCE');
    $kitchen = Department::query()->where('code', 'KITCHEN')->firstOrFail();
    $kitchenItem = Item::query()->where('canonical_sku', 'MKO-GULA-PASIR')->firstOrFail();
    $kitchenItem->update(['primary_department_id' => $kitchen->id]);

    $response = $this->actingAs($finance)->get(route('operations.inventory-count.opname-recap', [
        'outlet_id' => $this->outlet->id,
        // department_id sengaja TIDAK dikirim -- berarti "Semua Departemen".
    ]));

    $response->assertOk();
    $response->assertSee('Semua Departemen');
    $response->assertSee($this->bar->name.' — ', false);
    $response->assertSee($kitchen->name.' — ', false);
});

test('a department-scoped staff can never trigger "Semua Departemen" even if they try to omit department_id', function (): void {
    $staff = recapDashboardUser('STAFF_BAR', 'MKO_OUTLET_1', 'BAR');

    $response = $this->actingAs($staff)->get(route('operations.inventory-count.opname-recap'));

    $response->assertOk();
    $response->assertDontSee('Semua Departemen');
});
