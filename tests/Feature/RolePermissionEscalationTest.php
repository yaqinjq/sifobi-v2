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

    $this->admin = User::query()->where('email', 'admin@sifobi.test')->firstOrFail();
});

/**
 * Buat role lewat endpoint HTTP asli (sebagai SUPER_ADMIN) -- menghindari
 * seluk-beluk Spatie Teams kalau bikin Role/permission langsung lewat model
 * di tengah test, dan sekalian menguji jalur yang sama persis dipakai user
 * sungguhan.
 */
function createRoleViaHttp(Tests\TestCase $test, User $admin, string $name): Role
{
    $test->actingAs($admin)->post(route('settings.roles.store'), ['name' => $name]);

    return Role::where('name', $name)->where('team_id', $admin->tenant_id)->firstOrFail();
}

/**
 * Role custom yang punya manage_users (jadi bisa buka Settings > Manajemen
 * Role) tapi BUKAN SUPER_ADMIN -- ini persis skenario penyerangnya: user
 * biasa yang kebetulan/sengaja diberi manage_users, mencoba eskalasi lebih
 * jauh lewat role custom.
 */
function managerWithManageUsers(Tests\TestCase $test, User $admin): User
{
    $role = createRoleViaHttp($test, $admin, 'CUSTOM_HR_MANAGER');

    // settings.roles.update ada di dalam Route::prefix('settings') yang
    // sendiri sudah mewajibkan manage_settings (lihat routes/web.php),
    // BARU di dalamnya ada middleware permission:manage_users lagi khusus
    // untuk grup users/roles -- jadi untuk sekadar bisa BUKA halaman
    // Manajemen Role, butuh KEDUANYA, bukan cuma manage_users saja.
    $test->actingAs($admin)->put(route('settings.roles.update', $role), [
        'permissions' => ['manage_settings', 'manage_users'],
    ]);

    $user = User::factory()->create([
        'tenant_id' => $admin->tenant_id,
        'name' => 'HR Manager',
        'email' => 'hr.manager@sifobi.test',
        'status' => 'ACTIVE',
    ]);
    $user->assignRole('CUSTOM_HR_MANAGER');

    return $user;
}

test('non super admin with manage_users cannot grant manage_core to a custom role', function (): void {
    $attacker = managerWithManageUsers($this, $this->admin);
    $targetRole = createRoleViaHttp($this, $this->admin, 'SUPERVISOR_X');

    $this->actingAs($attacker)
        ->put(route('settings.roles.update', $targetRole), [
            'permissions' => ['manage_core', 'view_dashboard'],
        ])
        ->assertSessionHas('error');

    expect($targetRole->fresh()->hasPermissionTo('manage_core'))->toBeFalse()
        ->and($targetRole->fresh()->hasPermissionTo('view_dashboard'))->toBeFalse();
});

test('non super admin with manage_users cannot grant manage_users itself to escalate', function (): void {
    $attacker = managerWithManageUsers($this, $this->admin);
    $targetRole = createRoleViaHttp($this, $this->admin, 'SUPERVISOR_Y');

    $this->actingAs($attacker)
        ->put(route('settings.roles.update', $targetRole), [
            'permissions' => ['manage_users'],
        ])
        ->assertSessionHas('error');

    expect($targetRole->fresh()->hasPermissionTo('manage_users'))->toBeFalse();
});

test('non super admin with manage_users can still grant ordinary permissions', function (): void {
    $attacker = managerWithManageUsers($this, $this->admin);
    $targetRole = createRoleViaHttp($this, $this->admin, 'SUPERVISOR_Z');

    $this->actingAs($attacker)
        ->put(route('settings.roles.update', $targetRole), [
            'permissions' => ['view_dashboard', 'view_inventory'],
        ])
        ->assertSessionHas('success');

    expect($targetRole->fresh()->hasPermissionTo('view_dashboard'))->toBeTrue()
        ->and($targetRole->fresh()->hasPermissionTo('view_inventory'))->toBeTrue();
});

test('non super admin editing a role that already has a protected permission does not lose it', function (): void {
    $attacker = managerWithManageUsers($this, $this->admin);
    $targetRole = createRoleViaHttp($this, $this->admin, 'SUPERVISOR_W');

    $this->actingAs($this->admin)->put(route('settings.roles.update', $targetRole), [
        'permissions' => ['manage_settings'],
    ]);

    $this->actingAs($attacker)
        ->put(route('settings.roles.update', $targetRole), [
            'permissions' => ['manage_settings', 'view_dashboard'],
        ])
        ->assertSessionHas('success');

    expect($targetRole->fresh()->hasPermissionTo('manage_settings'))->toBeTrue()
        ->and($targetRole->fresh()->hasPermissionTo('view_dashboard'))->toBeTrue();
});

test('super admin can grant manage_core to a custom role', function (): void {
    $targetRole = createRoleViaHttp($this, $this->admin, 'SUPERVISOR_V');

    $this->actingAs($this->admin)
        ->put(route('settings.roles.update', $targetRole), [
            'permissions' => ['manage_core'],
        ])
        ->assertSessionHas('success');

    expect($targetRole->fresh()->hasPermissionTo('manage_core'))->toBeTrue();
});
