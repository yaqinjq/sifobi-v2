<?php

use App\Models\User;
use Database\Seeders\MinimumMasterDataSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed([
        RolesAndPermissionsSeeder::class,
        MinimumMasterDataSeeder::class,
    ]);
});

test('guest cannot access dashboard', function (): void {
    $this->get('/dashboard')
        ->assertRedirect('/login');
});

test('super admin can access dashboard', function (): void {
    $user = User::query()->where('email', 'admin@sifobi.test')->firstOrFail();

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertOk()
        ->assertSee('Super Admin')
        ->assertSee('SUPER_ADMIN');
});

test('user without permission cannot access permission protected route', function (): void {
    $user = User::factory()->create([
        'tenant_id' => User::query()->where('email', 'admin@sifobi.test')->value('tenant_id'),
        'name' => 'Plain User',
        'email' => 'plain@sifobi.test',
        'status' => 'ACTIVE',
    ]);

    $this->actingAs($user)
        ->get('/admin/core')
        ->assertForbidden();
});

test('create_po and approve_po permissions exist', function (): void {
    expect(Permission::query()->where('name', 'create_po')->exists())->toBeTrue()
        ->and(Permission::query()->where('name', 'approve_po')->exists())->toBeTrue()
        ->and(Permission::query()->where('name', 'manage_settings')->exists())->toBeTrue()
        ->and(Permission::query()->where('name', 'manage_brands_outlets')->exists())->toBeTrue()
        ->and(Permission::query()->where('name', 'manage_integrations')->exists())->toBeTrue();
});

test('settings permission is limited to finance admin roles', function (): void {
    expect(Role::findByName('SUPER_ADMIN')->hasPermissionTo('manage_settings'))->toBeTrue()
        ->and(Role::findByName('ADMIN')->hasPermissionTo('manage_settings'))->toBeTrue()
        ->and(Role::findByName('GENERAL_FINANCE')->hasPermissionTo('manage_settings'))->toBeTrue()
        ->and(Role::findByName('FINANCE_STAFF')->hasPermissionTo('manage_settings'))->toBeFalse()
        ->and(Role::findByName('STAFF_BAR')->hasPermissionTo('manage_settings'))->toBeFalse();
});

test('brand outlet and integration permissions are admin only', function (): void {
    expect(Role::findByName('SUPER_ADMIN')->hasPermissionTo('manage_brands_outlets'))->toBeTrue()
        ->and(Role::findByName('ADMIN')->hasPermissionTo('manage_brands_outlets'))->toBeTrue()
        ->and(Role::findByName('GENERAL_FINANCE')->hasPermissionTo('manage_brands_outlets'))->toBeFalse()
        ->and(Role::findByName('SUPER_ADMIN')->hasPermissionTo('manage_integrations'))->toBeTrue()
        ->and(Role::findByName('ADMIN')->hasPermissionTo('manage_integrations'))->toBeTrue()
        ->and(Role::findByName('GENERAL_FINANCE')->hasPermissionTo('manage_integrations'))->toBeFalse();
});

test('staff role can create po but cannot approve po', function (): void {
    $role = Role::findByName('STAFF_BAR');

    expect($role->hasPermissionTo('create_po'))->toBeTrue()
        ->and($role->hasPermissionTo('approve_po'))->toBeFalse();
});

test('PIC_OUTLET role can approve po', function (): void {
    $role = Role::findByName('PIC_OUTLET');

    expect($role->hasPermissionTo('approve_po'))->toBeTrue();
});
