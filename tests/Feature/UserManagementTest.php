<?php

use App\Models\User;
use App\Modules\Core\Models\Outlet;
use Database\Seeders\MinimumMasterDataSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed([
        RolesAndPermissionsSeeder::class,
        MinimumMasterDataSeeder::class,
    ]);

    $this->admin = User::query()->where('email', 'admin@sifobi.test')->firstOrFail();
});

test('admin can open user management page', function (): void {
    $this->actingAs($this->admin)
        ->get(route('settings.users.index'))
        ->assertOk()
        ->assertSee('Manajemen User')
        ->assertSee('Super Admin');
});

test('user without manage users cannot open user management page', function (): void {
    $user = User::factory()->create([
        'tenant_id' => $this->admin->tenant_id,
        'name' => 'Finance Viewer',
        'email' => 'finance.viewer@sifobi.test',
        'status' => 'ACTIVE',
    ]);
    $user->assignRole('GENERAL_FINANCE');

    $this->actingAs($user)
        ->get(route('settings.users.index'))
        ->assertForbidden();
});

test('admin can create user and assign role', function (): void {
    $outlet = Outlet::query()->where('tenant_id', $this->admin->tenant_id)->firstOrFail();

    $this->actingAs($this->admin)
        ->post(route('settings.users.store'), [
            'name' => 'Staff Bar Baru',
            'email' => 'staff.bar.baru@sifobi.test',
            'phone' => '081234567890',
            'password' => 'Password@123',
            'password_confirmation' => 'Password@123',
            'role' => 'STAFF_BAR',
            'outlet_id' => $outlet->id,
            'status' => 'active',
        ])
        ->assertRedirect(route('settings.users.index'));

    $user = User::query()->where('email', 'staff.bar.baru@sifobi.test')->firstOrFail();

    expect($user->tenant_id)->toBe($this->admin->tenant_id)
        ->and($user->outlet_id)->toBe($outlet->id)
        ->and($user->status)->toBe('ACTIVE')
        ->and($user->hasRole('STAFF_BAR'))->toBeTrue();
});

test('admin cannot deactivate own account', function (): void {
    $this->actingAs($this->admin)
        ->patch(route('settings.users.toggle-status', $this->admin))
        ->assertSessionHas('error');

    expect($this->admin->refresh()->status)->toBe('ACTIVE');
});
