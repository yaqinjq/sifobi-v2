<?php

use App\Models\User;
use App\Modules\Core\Models\Department;
use App\Modules\Core\Models\Tenant;
use App\Modules\Inventory\Models\Item;
use App\Modules\Inventory\Models\ItemCategory;
use App\Modules\Inventory\Models\Unit;
use Database\Seeders\MinimumMasterDataSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed([
        RolesAndPermissionsSeeder::class,
        MinimumMasterDataSeeder::class,
    ]);

    $this->admin = User::query()->where('email', 'admin@sifobi.test')->firstOrFail();
    $this->tenant = Tenant::query()->where('code', 'MKO')->firstOrFail();
    $this->kitchen = Department::query()->where('code', 'KITCHEN')->firstOrFail();
    $this->bar = Department::query()->where('code', 'BAR')->firstOrFail();

    $this->wiproCategory = ItemCategory::query()->create([
        'tenant_id' => $this->tenant->id,
        'code' => 'WIPRO_BUMBU',
        'name' => 'Wipro — Bumbu',
        'status' => 'ACTIVE',
        'is_active' => true,
    ]);

    $gram = Unit::query()->where('tenant_id', $this->tenant->id)->where('code', 'GR')->firstOrFail();

    $this->wiproItem = Item::query()->create([
        'tenant_id' => $this->tenant->id,
        'item_category_id' => $this->wiproCategory->id,
        'name' => 'Bumbu Tomyum Wipro',
        'canonical_sku' => 'WIPRO-TOMYUM-001',
        'item_source' => 'WIPRO',
        'item_type' => 'WIP_L1',
        'inventory_unit_id' => $gram->id,
        'purchase_unit_id' => $gram->id,
        'base_unit_id' => $gram->id,
        'track_stock' => false,
        'is_active' => true,
    ]);
});

test('bulk activate sets track_stock and department and maps the category', function (): void {
    $this->actingAs($this->admin)
        ->post(route('master-data.wipro-items.bulk-activate-opname'), [
            'department_id' => $this->kitchen->id,
            'ids' => [$this->wiproItem->id],
        ])
        ->assertRedirect(route('master-data.wipro-items.index'));

    $this->wiproItem->refresh();

    expect($this->wiproItem->track_stock)->toBeTrue()
        ->and($this->wiproItem->primary_department_id)->toBe($this->kitchen->id)
        ->and($this->kitchen->itemCategories()->where('item_categories.id', $this->wiproCategory->id)->exists())->toBeTrue();
});

test('activated wipro item now appears in opname items for that department', function (): void {
    $this->actingAs($this->admin)->post(route('master-data.wipro-items.bulk-activate-opname'), [
        'department_id' => $this->kitchen->id,
        'ids' => [$this->wiproItem->id],
    ]);

    $staff = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'outlet_id' => \App\Modules\Core\Models\Outlet::query()->where('tenant_id', $this->tenant->id)->value('id'),
        'department_id' => $this->kitchen->id,
        'status' => 'ACTIVE',
    ]);
    $staff->assignRole('STAFF_BAR');

    $session = app(\App\Services\OpnameService::class)->startSession([
        'tenant_id' => $this->tenant->id,
        'outlet_id' => $staff->outlet_id,
        'department_id' => $this->kitchen->id,
        'type' => \App\Modules\Operations\Models\OpnameSession::TYPE_DAILY,
        'opname_date' => now()->toDateString(),
    ], $staff->id);

    expect($session->items->pluck('item_id')->all())->toContain($this->wiproItem->id);
});

test('bulk activation survives a subsequent wipro catalog re-import', function (): void {
    $this->actingAs($this->admin)->post(route('master-data.wipro-items.bulk-activate-opname'), [
        'department_id' => $this->kitchen->id,
        'ids' => [$this->wiproItem->id],
    ]);

    $path = storage_path('framework/testing/wipro-catalog-reimport.xlsx');
    File::ensureDirectoryExists(dirname($path));

    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->fromArray([
        ['sku', 'nama', 'tipe', 'unit', 'aktif', 'kategori', 'ck', 'konversi'],
        ['WIPRO-TOMYUM-001', 'Bumbu Tomyum Wipro', '', 'GR', 1, 'Bumbu', '', ''],
    ]);
    (new Xlsx($spreadsheet))->save($path);

    (new \App\Imports\WiproCatalogImport($this->tenant->id))->import($path);

    expect($this->wiproItem->refresh()->track_stock)->toBeTrue()
        ->and($this->wiproItem->primary_department_id)->toBe($this->kitchen->id);
});

test('re-running bulk activate on an already active item changes its department', function (): void {
    $this->actingAs($this->admin)->post(route('master-data.wipro-items.bulk-activate-opname'), [
        'department_id' => $this->kitchen->id,
        'ids' => [$this->wiproItem->id],
    ]);

    expect($this->wiproItem->refresh()->primary_department_id)->toBe($this->kitchen->id);

    $this->actingAs($this->admin)
        ->post(route('master-data.wipro-items.bulk-activate-opname'), [
            'department_id' => $this->bar->id,
            'ids' => [$this->wiproItem->id],
        ])
        ->assertRedirect(route('master-data.wipro-items.index'));

    expect($this->wiproItem->refresh()->track_stock)->toBeTrue()
        ->and($this->wiproItem->primary_department_id)->toBe($this->bar->id)
        ->and($this->bar->itemCategories()->where('item_categories.id', $this->wiproCategory->id)->exists())->toBeTrue();
});

test('bulk deactivate clears track_stock and department so item no longer appears in opname', function (): void {
    $this->actingAs($this->admin)->post(route('master-data.wipro-items.bulk-activate-opname'), [
        'department_id' => $this->kitchen->id,
        'ids' => [$this->wiproItem->id],
    ]);
    expect($this->wiproItem->refresh()->track_stock)->toBeTrue();

    $this->actingAs($this->admin)
        ->post(route('master-data.wipro-items.bulk-deactivate-opname'), [
            'ids' => [$this->wiproItem->id],
        ])
        ->assertRedirect(route('master-data.wipro-items.index'));

    $this->wiproItem->refresh();
    expect($this->wiproItem->track_stock)->toBeFalse()
        ->and($this->wiproItem->primary_department_id)->toBeNull();

    $staff = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'outlet_id' => \App\Modules\Core\Models\Outlet::query()->where('tenant_id', $this->tenant->id)->value('id'),
        'department_id' => $this->kitchen->id,
        'status' => 'ACTIVE',
    ]);
    $staff->assignRole('STAFF_BAR');

    $session = app(\App\Services\OpnameService::class)->startSession([
        'tenant_id' => $this->tenant->id,
        'outlet_id' => $staff->outlet_id,
        'department_id' => $this->kitchen->id,
        'type' => \App\Modules\Operations\Models\OpnameSession::TYPE_DAILY,
        'opname_date' => now()->toDateString(),
    ], $staff->id);

    expect($session->items->pluck('item_id')->all())->not->toContain($this->wiproItem->id);
});

test('non wipro items and items from another tenant are rejected', function (): void {
    $internalItem = Item::query()->where('canonical_sku', 'MKO-AJINOMOTO-500GR')->firstOrFail();

    $this->actingAs($this->admin)
        ->post(route('master-data.wipro-items.bulk-activate-opname'), [
            'department_id' => $this->kitchen->id,
            'ids' => [$internalItem->id],
        ])
        ->assertSessionHasErrors('ids.0');

    expect($internalItem->refresh()->primary_department_id)->toBeNull();
});
