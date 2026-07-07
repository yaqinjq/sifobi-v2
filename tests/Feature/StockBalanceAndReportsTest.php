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
