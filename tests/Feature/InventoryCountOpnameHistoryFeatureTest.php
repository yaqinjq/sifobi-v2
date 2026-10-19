<?php

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

    Item::query()->where('canonical_sku', 'MKO-AJINOMOTO-500GR')->update(['primary_department_id' => $this->bar->id]);
    Item::query()->where('canonical_sku', 'MKO-GULA-PASIR')->update(['primary_department_id' => $this->kitchen->id]);
});

test('staff sees their own department existing opname sessions without touching any input form', function (): void {
    $staff = opnameUser('STAFF_BAR', 'BAR');

    $session = app(OpnameService::class)->startSession([
        'tenant_id' => $this->tenant->id,
        'outlet_id' => $this->outlet->id,
        'department_id' => $this->bar->id,
        'type' => OpnameSession::TYPE_DAILY,
        'opname_date' => now()->toDateString(),
    ], $staff->id);

    $response = $this->actingAs($staff)->get(route('operations.inventory-count.opname-history'));

    $response->assertOk();
    $response->assertSee(route('operations.opname.show', $session), false);
});

test('super admin must pick outlet and department before any session shows', function (): void {
    $admin = \App\Models\User::query()->where('email', 'admin@sifobi.test')->firstOrFail();

    app(OpnameService::class)->startSession([
        'tenant_id' => $this->tenant->id,
        'outlet_id' => $this->outlet->id,
        'department_id' => $this->bar->id,
        'type' => OpnameSession::TYPE_DAILY,
        'opname_date' => now()->toDateString(),
    ], $admin->id);

    $response = $this->actingAs($admin)->get(route('operations.inventory-count.opname-history'));

    $response->assertOk();
    $response->assertSee('Belum ada scope outlet & departemen yang sah');
});

test('super admin sees the session after picking the matching outlet and department', function (): void {
    $admin = \App\Models\User::query()->where('email', 'admin@sifobi.test')->firstOrFail();

    $session = app(OpnameService::class)->startSession([
        'tenant_id' => $this->tenant->id,
        'outlet_id' => $this->outlet->id,
        'department_id' => $this->bar->id,
        'type' => OpnameSession::TYPE_DAILY,
        'opname_date' => now()->toDateString(),
    ], $admin->id);

    $response = $this->actingAs($admin)->get(route('operations.inventory-count.opname-history', [
        'outlet_id' => $this->outlet->id,
        'department_id' => $this->bar->id,
    ]));

    $response->assertOk();
    $response->assertSee(route('operations.opname.show', $session), false);
});

test('bar staff never sees a kitchen session through this read-only page', function (): void {
    $barStaff = opnameUser('STAFF_BAR', 'BAR');
    $kitchenStaff = opnameUser('STAFF_KITCHEN', 'KITCHEN');

    $kitchenSession = app(OpnameService::class)->startSession([
        'tenant_id' => $this->tenant->id,
        'outlet_id' => $this->outlet->id,
        'department_id' => $this->kitchen->id,
        'type' => OpnameSession::TYPE_DAILY,
        'opname_date' => now()->toDateString(),
    ], $kitchenStaff->id);

    $response = $this->actingAs($barStaff)->get(route('operations.inventory-count.opname-history'));

    $response->assertOk();
    $response->assertDontSee(route('operations.opname.show', $kitchenSession), false);
});

test('route is GET-only -- no write method is registered for it', function (): void {
    expect(\Illuminate\Support\Facades\Route::getRoutes()->getByName('operations.inventory-count.opname-history')->methods())
        ->toBe(['GET', 'HEAD']);
});
