<?php

use App\Models\User;
use Database\Seeders\MinimumMasterDataSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed([
        RolesAndPermissionsSeeder::class,
        MinimumMasterDataSeeder::class,
    ]);
});

test('guest cannot access stock balance and reports', function (): void {
    $this->get('/stock/balance')->assertRedirect('/login');
    $this->get('/laporan')->assertRedirect('/login');
});

test('super admin can access stock balance and reports', function (): void {
    $user = User::query()->where('email', 'admin@sifobi.test')->firstOrFail();

    $this->actingAs($user)
        ->get('/stock/balance')
        ->assertOk()
        ->assertSee('Stok Gudang');

    $this->actingAs($user)
        ->get('/laporan')
        ->assertOk()
        ->assertSee('Laporan');
});

test('staff can view stock balance but cannot view reports', function (): void {
    $admin = User::query()->where('email', 'admin@sifobi.test')->firstOrFail();

    $user = User::factory()->create([
        'tenant_id' => $admin->tenant_id,
        'outlet_id' => $admin->outlet_id,
        'name' => 'Staff Bar',
        'email' => 'staffbar@sifobi.test',
        'status' => 'ACTIVE',
    ]);
    $user->assignRole('STAFF_BAR');

    $this->actingAs($user)
        ->get('/stock/balance')
        ->assertOk();

    $this->actingAs($user)
        ->get('/laporan')
        ->assertForbidden();
});

test('report and stock permissions are assigned to expected roles', function (): void {
    expect(Role::findByName('SUPER_ADMIN')->hasPermissionTo('view_all_reports'))->toBeTrue()
        ->and(Role::findByName('ADMIN')->hasPermissionTo('view_all_reports'))->toBeTrue()
        ->and(Role::findByName('MANAGER_AREA')->hasPermissionTo('view_all_reports'))->toBeTrue()
        ->and(Role::findByName('GENERAL_FINANCE')->hasPermissionTo('view_all_reports'))->toBeTrue()
        ->and(Role::findByName('STAFF_BAR')->hasPermissionTo('view_reports'))->toBeFalse()
        ->and(Role::findByName('STAFF_KITCHEN')->hasPermissionTo('view_reports'))->toBeFalse()
        ->and(Role::findByName('STAFF_BAR')->hasPermissionTo('view_stock_balance'))->toBeTrue();
});

test('mutation report export responds successfully', function (): void {
    $user = User::query()->where('email', 'admin@sifobi.test')->firstOrFail();

    $this->actingAs($user)
        ->get('/laporan/mutasi/export')
        ->assertOk();
});

test('kartu stok ringkasan renders without outlet_id by defaulting to first outlet', function (): void {
    $user = User::query()->where('email', 'admin@sifobi.test')->firstOrFail();

    $this->actingAs($user)
        ->get(route('laporan.kartu-stok'))
        ->assertOk()
        ->assertSee('Kartu Stok');
});

test('kartu stok detail renders without outlet_id by defaulting to first outlet', function (): void {
    $user = User::query()->where('email', 'admin@sifobi.test')->firstOrFail();
    $item = \App\Modules\Inventory\Models\Item::query()->where('canonical_sku', 'MKO-AJINOMOTO-500GR')->firstOrFail();

    $this->actingAs($user)
        ->get(route('laporan.kartu-stok.detail', ['item' => $item->id]))
        ->assertOk();
});

test('kartu stok ringkasan rows include category_name for client-side filtering', function (): void {
    $user = User::query()->where('email', 'admin@sifobi.test')->firstOrFail();
    $outlet = \App\Modules\Core\Models\Outlet::query()->where('tenant_id', $user->tenant_id)->firstOrFail();
    $item = \App\Modules\Inventory\Models\Item::query()->where('canonical_sku', 'MKO-AJINOMOTO-500GR')->firstOrFail();

    $this->actingAs($user)->post('/operations/open-stocks', [
        'outlet_id' => $outlet->id,
        'stock_target' => \App\Modules\Operations\Models\OpenStock::TARGET_OUTLET_DAILY,
        'business_date' => now()->toDateString(),
        'items' => [
            [
                'item_id' => $item->id,
                'department_id' => \App\Modules\Core\Models\Department::query()->where('tenant_id', $user->tenant_id)->firstOrFail()->id,
                'qty_whole' => '10',
                'qty_loose' => '0',
                'cost_per_unit' => '12000',
            ],
        ],
    ]);
    $openStock = \App\Modules\Operations\Models\OpenStock::query()->firstOrFail();
    $this->actingAs($user)->post("/operations/open-stocks/{$openStock->id}/post");

    $response = $this->actingAs($user)
        ->get(route('laporan.kartu-stok', ['outlet_id' => $outlet->id]))
        ->assertOk();

    $response->assertSee('category_name', false);
    $response->assertSee('Ajinomoto 500gr', false);
});
