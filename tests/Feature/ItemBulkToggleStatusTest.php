<?php

use App\Models\User;
use App\Modules\Core\Models\Tenant;
use App\Modules\Inventory\Models\Item;
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

test('bulk toggle deactivates only currently active items', function (): void {
    $item1 = Item::query()->where('canonical_sku', 'MKO-AJINOMOTO-500GR')->firstOrFail();
    $item2 = Item::query()->where('canonical_sku', 'MKO-GULA-PASIR')->firstOrFail();
    $item1->update(['is_active' => true]);
    $item2->update(['is_active' => false]);

    $this->actingAs($this->admin)
        ->post(route('master-data.items.bulk-toggle-active'), [
            'active' => '0',
            'ids' => [$item1->id, $item2->id],
        ])
        ->assertRedirect(route('master-data.items.index'));

    expect($item1->refresh()->is_active)->toBeFalse()
        ->and($item2->refresh()->is_active)->toBeFalse();
});

test('bulk toggle activates only currently inactive items', function (): void {
    $item1 = Item::query()->where('canonical_sku', 'MKO-AJINOMOTO-500GR')->firstOrFail();
    $item1->update(['is_active' => false]);

    $this->actingAs($this->admin)
        ->post(route('master-data.items.bulk-toggle-active'), [
            'active' => '1',
            'ids' => [$item1->id],
        ])
        ->assertRedirect(route('master-data.items.index'));

    expect($item1->refresh()->is_active)->toBeTrue();
});

test('user without manage_items cannot bulk toggle item status', function (): void {
    $item = Item::query()->where('canonical_sku', 'MKO-AJINOMOTO-500GR')->firstOrFail();
    $tenant = Tenant::query()->where('code', 'MKO')->firstOrFail();

    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'status' => 'ACTIVE',
    ]);
    $user->assignRole('STAFF_BAR');

    $this->actingAs($user)
        ->post(route('master-data.items.bulk-toggle-active'), [
            'active' => '0',
            'ids' => [$item->id],
        ])
        ->assertForbidden();
});
