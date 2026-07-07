<?php

use App\Imports\ItemsImport;
use App\Models\User;
use App\Modules\Core\Models\Tenant;
use App\Modules\Inventory\Models\Item;
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

    $this->tenant = Tenant::query()->where('code', 'MKO')->firstOrFail();
});

function masterDataUser(string $roleName): User
{
    $tenant = Tenant::query()->where('code', 'MKO')->firstOrFail();

    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'name' => $roleName.' User',
        'email' => strtolower($roleName).'@sifobi.test',
        'status' => 'ACTIVE',
    ]);

    $user->assignRole($roleName);

    return $user;
}

test('master data import export permissions are seeded with expected role scope', function (): void {
    expect(Permission::query()->where('name', 'export_master_data')->exists())->toBeTrue()
        ->and(Permission::query()->where('name', 'import_master_data')->exists())->toBeTrue()
        ->and(Role::findByName('FINANCE_STAFF')->hasPermissionTo('export_master_data'))->toBeTrue()
        ->and(Role::findByName('FINANCE_STAFF')->hasPermissionTo('import_master_data'))->toBeFalse()
        ->and(Role::findByName('STAFF_BAR')->hasPermissionTo('export_master_data'))->toBeFalse()
        ->and(Role::findByName('STAFF_KITCHEN')->hasPermissionTo('export_master_data'))->toBeFalse()
        ->and(Role::findByName('GENERAL_FINANCE')->hasPermissionTo('import_master_data'))->toBeTrue();
});

test('guest cannot access import export page', function (): void {
    $this->get(route('master-data.ie.index'))
        ->assertRedirect('/login');
});

test('role without export permission cannot access import export page', function (): void {
    $user = masterDataUser('STAFF_BAR');

    $this->actingAs($user)
        ->get(route('master-data.ie.index'))
        ->assertForbidden();
});

test('finance staff can access export page but cannot import', function (): void {
    $user = masterDataUser('FINANCE_STAFF');

    $this->actingAs($user)
        ->get(route('master-data.ie.index'))
        ->assertOk()
        ->assertSee('Export Data')
        ->assertSee('hanya memiliki akses export');

    $this->actingAs($user)
        ->postJson(route('master-data.ie.import.items'))
        ->assertForbidden();
});

test('admin can download item template and items export', function (): void {
    $user = User::query()->where('email', 'admin@sifobi.test')->firstOrFail();

    $this->actingAs($user)
        ->get(route('master-data.ie.template.items'))
        ->assertOk();

    $this->actingAs($user)
        ->get(route('master-data.ie.export.items'))
        ->assertOk();
});

test('items import updates existing rows inserts new rows and collects row errors', function (): void {
    $import = new ItemsImport($this->tenant->id);

    $import->collection(collect([
        collect([
            'canonical_sku' => 'MKO-AJINOMOTO-500GR',
            'name' => 'Ajinomoto MSG 500g Updated',
            'description' => 'Updated by import',
            'item_category' => 'Bumbu',
            'item_type' => 'BAHAN_BAKU',
            'base_unit' => 'gr',
            'inventory_unit' => 'gr',
            'purchase_unit' => 'pack',
            'inventory_ratio' => '500',
            'purchase_ratio' => '12000',
            'yield_pct' => '100',
            'last_purchase_price' => '26000',
            'is_active' => '1',
        ]),
        collect([
            'canonical_sku' => 'BCF-NEW-001',
            'name' => 'Bahan Baru Import',
            'description' => 'Inserted by import',
            'item_category' => 'Bumbu',
            'item_type' => 'BAHAN_BAKU',
            'base_unit' => 'gr',
            'inventory_unit' => 'gr',
            'purchase_unit' => 'pack',
            'inventory_ratio' => '1',
            'purchase_ratio' => '1000',
            'yield_pct' => '100',
            'last_purchase_price' => '15000',
            'is_active' => '1',
        ]),
        collect([
            'canonical_sku' => 'BCF-BAD-001',
            'name' => 'Bahan Error',
            'description' => 'Bad unit',
            'item_category' => 'Bumbu',
            'item_type' => 'BAHAN_BAKU',
            'base_unit' => 'liter',
            'inventory_unit' => 'liter',
            'purchase_unit' => 'pack',
            'inventory_ratio' => '1',
            'purchase_ratio' => '1000',
            'yield_pct' => '100',
            'last_purchase_price' => '15000',
            'is_active' => '1',
        ]),
    ]));

    $summary = $import->summary();

    expect($summary['inserted'])->toBe(1)
        ->and($summary['updated'])->toBe(1)
        ->and($summary['failed'])->toBe(1)
        ->and($summary['errors'][0]['message'])->toContain("Unit 'liter' tidak ditemukan");

    $this->assertDatabaseHas('items', [
        'tenant_id' => $this->tenant->id,
        'canonical_sku' => 'MKO-AJINOMOTO-500GR',
        'name' => 'Ajinomoto MSG 500g Updated',
        'description' => 'Updated by import',
    ]);

    $this->assertDatabaseHas('items', [
        'tenant_id' => $this->tenant->id,
        'canonical_sku' => 'BCF-NEW-001',
        'name' => 'Bahan Baru Import',
    ]);

    expect(Item::query()->where('canonical_sku', 'BCF-BAD-001')->exists())->toBeFalse();
});
