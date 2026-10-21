<?php

use App\Models\User;
use App\Modules\Core\Models\Brand;
use App\Modules\Core\Models\LegalEntity;
use App\Modules\Core\Models\Outlet;
use App\Modules\Core\Models\Tenant;
use Database\Seeders\MinimumMasterDataSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Tidak ada factory utk Brand/LegalEntity/Outlet di codebase ini --
 * pola yang sama dgn foreignOutletInNewTenant() di
 * SpoilWasteDepartmentScopingFeatureTest.php, cuma diberi nama beda
 * supaya tidak bentrok (Pest memuat semua file test dlm 1 namespace
 * global utk fungsi top-level).
 */
function foreignOutletInNewTenantForUserTest(): Outlet
{
    $suffix = uniqid();
    $otherTenant = Tenant::query()->create(['code' => 'OTHERTEN_'.$suffix, 'name' => 'Other Tenant '.$suffix, 'status' => 'ACTIVE']);
    $otherBrand = Brand::withoutGlobalScopes()->create(['tenant_id' => $otherTenant->id, 'code' => 'OTB_'.$suffix, 'name' => 'Other Brand', 'status' => 'ACTIVE']);
    $otherLegalEntity = LegalEntity::withoutGlobalScopes()->create(['tenant_id' => $otherTenant->id, 'code' => 'OTLE_'.$suffix, 'name' => 'Other Legal Entity', 'status' => 'ACTIVE']);

    return Outlet::withoutGlobalScopes()->create([
        'tenant_id' => $otherTenant->id,
        'brand_id' => $otherBrand->id,
        'legal_entity_id' => $otherLegalEntity->id,
        'code' => 'OTO_'.$suffix,
        'name' => 'Foreign Outlet',
        'status' => 'ACTIVE',
    ]);
}

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

test('user management page can be filtered by outlet', function (): void {
    $outlet1 = Outlet::query()->where('tenant_id', $this->admin->tenant_id)->where('code', 'MKO_OUTLET_1')->firstOrFail();
    $outlet2 = Outlet::query()->where('tenant_id', $this->admin->tenant_id)->where('code', 'MKO_OUTLET_2')->firstOrFail();

    $staffOutlet1 = User::factory()->create([
        'tenant_id' => $this->admin->tenant_id,
        'outlet_id' => $outlet1->id,
        'name' => 'Staff Outlet Satu',
        'email' => 'staff.outlet1@sifobi.test',
        'status' => 'ACTIVE',
    ]);
    $staffOutlet2 = User::factory()->create([
        'tenant_id' => $this->admin->tenant_id,
        'outlet_id' => $outlet2->id,
        'name' => 'Staff Outlet Dua',
        'email' => 'staff.outlet2@sifobi.test',
        'status' => 'ACTIVE',
    ]);

    $response = $this->actingAs($this->admin)
        ->get(route('settings.users.index', ['outlet_id' => $outlet1->id]));

    $response->assertOk()
        ->assertSee('Staff Outlet Satu')
        ->assertDontSee('Staff Outlet Dua');

    $response = $this->actingAs($this->admin)
        ->get(route('settings.users.index', ['outlet_id' => $outlet2->id]));

    $response->assertOk()
        ->assertSee('Staff Outlet Dua')
        ->assertDontSee('Staff Outlet Satu');
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

test('creating an outlet-scoped role without an outlet is rejected', function (): void {
    $this->actingAs($this->admin)
        ->post(route('settings.users.store'), [
            'name' => 'Staff Kitchen Tanpa Outlet',
            'email' => 'staff.kitchen.no-outlet@sifobi.test',
            'phone' => '081234567891',
            'password' => 'Password@123',
            'password_confirmation' => 'Password@123',
            'role' => 'STAFF_KITCHEN',
            'status' => 'active',
        ])
        ->assertSessionHasErrors('outlet_id');

    expect(User::query()->where('email', 'staff.kitchen.no-outlet@sifobi.test')->exists())->toBeFalse();
});

test('admin cannot deactivate own account', function (): void {
    $this->actingAs($this->admin)
        ->patch(route('settings.users.toggle-status', $this->admin))
        ->assertSessionHas('error');

    expect($this->admin->refresh()->status)->toBe('ACTIVE');
});

test('bulk toggle status deactivates selected active users but skips own account', function (): void {
    $outlet = Outlet::query()->where('tenant_id', $this->admin->tenant_id)->firstOrFail();

    $user1 = User::factory()->create(['tenant_id' => $this->admin->tenant_id, 'outlet_id' => $outlet->id, 'status' => 'ACTIVE']);
    $user2 = User::factory()->create(['tenant_id' => $this->admin->tenant_id, 'outlet_id' => $outlet->id, 'status' => 'ACTIVE']);

    $this->actingAs($this->admin)
        ->post(route('settings.users.bulk-toggle-status'), [
            'status' => 'INACTIVE',
            'ids' => [$user1->id, $user2->id, $this->admin->id],
        ])
        ->assertRedirect(route('settings.users.index'));

    expect($user1->refresh()->status)->toBe('INACTIVE')
        ->and($user2->refresh()->status)->toBe('INACTIVE')
        ->and($this->admin->refresh()->status)->toBe('ACTIVE');
});

test('bulk toggle status activates selected inactive users', function (): void {
    $outlet = Outlet::query()->where('tenant_id', $this->admin->tenant_id)->firstOrFail();
    $user1 = User::factory()->create(['tenant_id' => $this->admin->tenant_id, 'outlet_id' => $outlet->id, 'status' => 'INACTIVE']);

    $this->actingAs($this->admin)
        ->post(route('settings.users.bulk-toggle-status'), [
            'status' => 'ACTIVE',
            'ids' => [$user1->id],
        ])
        ->assertRedirect(route('settings.users.index'));

    expect($user1->refresh()->status)->toBe('ACTIVE');
});

test('bulk change outlet moves only the selected users to the chosen outlet', function (): void {
    $outletFrom = Outlet::query()->where('tenant_id', $this->admin->tenant_id)->where('code', 'MKO_OUTLET_1')->firstOrFail();
    $outletTo = Outlet::query()->where('tenant_id', $this->admin->tenant_id)->where('code', 'MKO_OUTLET_2')->firstOrFail();

    $moved = User::factory()->create(['tenant_id' => $this->admin->tenant_id, 'outlet_id' => $outletFrom->id]);
    $untouched = User::factory()->create(['tenant_id' => $this->admin->tenant_id, 'outlet_id' => $outletFrom->id]);

    $this->actingAs($this->admin)
        ->post(route('settings.users.bulk-change-outlet'), [
            'outlet_id' => $outletTo->id,
            'ids' => [$moved->id],
        ])
        ->assertRedirect(route('settings.users.index'));

    expect($moved->refresh()->outlet_id)->toBe($outletTo->id)
        ->and($untouched->refresh()->outlet_id)->toBe($outletFrom->id);
});

test('bulk change outlet to an outlet from another tenant is rejected', function (): void {
    $foreignOutlet = foreignOutletInNewTenantForUserTest();
    $user = User::factory()->create(['tenant_id' => $this->admin->tenant_id]);

    $this->actingAs($this->admin)
        ->post(route('settings.users.bulk-change-outlet'), [
            'outlet_id' => $foreignOutlet->id,
            'ids' => [$user->id],
        ])
        ->assertSessionHasErrors('outlet_id');
});

test('bulk delete removes only users who never logged in, skips the rest and own account', function (): void {
    $neverLoggedIn = User::factory()->create(['tenant_id' => $this->admin->tenant_id, 'last_login_at' => null]);
    $hasLoggedIn = User::factory()->create(['tenant_id' => $this->admin->tenant_id, 'last_login_at' => now()->subDay()]);

    $this->actingAs($this->admin)
        ->post(route('settings.users.bulk-delete'), [
            'ids' => [$neverLoggedIn->id, $hasLoggedIn->id, $this->admin->id],
        ])
        ->assertRedirect(route('settings.users.index'));

    expect(User::query()->find($neverLoggedIn->id))->toBeNull()
        ->and(User::query()->find($hasLoggedIn->id))->not->toBeNull()
        ->and(User::query()->find($this->admin->id))->not->toBeNull();
});
