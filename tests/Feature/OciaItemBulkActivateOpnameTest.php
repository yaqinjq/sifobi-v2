<?php

use App\Models\User;
use App\Modules\Core\Models\Department;
use App\Modules\Core\Models\Tenant;
use App\Modules\Inventory\Models\Item;
use App\Modules\Inventory\Models\ItemCategory;
use App\Modules\Inventory\Models\Unit;
use App\Services\OciaCatalogSyncService;
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
    $this->tenant = Tenant::query()->where('code', 'MKO')->firstOrFail();
    $this->kitchen = Department::query()->where('code', 'KITCHEN')->firstOrFail();
    $this->bar = Department::query()->where('code', 'BAR')->firstOrFail();

    $this->ociaCategory = ItemCategory::query()->create([
        'tenant_id' => $this->tenant->id,
        'code' => 'OCIA_BLENDED',
        'name' => 'OCIA — Blended',
        'status' => 'ACTIVE',
        'is_active' => true,
    ]);

    $kg = Unit::query()->where('tenant_id', $this->tenant->id)->where('code', 'KG')->first()
        ?? Unit::query()->create(['tenant_id' => $this->tenant->id, 'code' => 'KG', 'name' => 'KG', 'abbreviation' => 'kg']);

    $this->ociaItem = Item::query()->create([
        'tenant_id' => $this->tenant->id,
        'item_category_id' => $this->ociaCategory->id,
        'name' => 'Arabica Blend OCIA',
        'canonical_sku' => 'OCIA-ARABICA-001',
        'item_source' => 'OCIA',
        'ocia_product_id' => 1,
        'item_type' => 'BAHAN_BAKU',
        'inventory_unit_id' => $kg->id,
        'purchase_unit_id' => $kg->id,
        'base_unit_id' => $kg->id,
        'track_stock' => false,
        'is_active' => true,
    ]);
});

test('bulk activate sets track_stock and department and maps the category', function (): void {
    $this->actingAs($this->admin)
        ->post(route('master-data.ocia-items.bulk-activate-opname'), [
            'department_id' => $this->kitchen->id,
            'ids' => [$this->ociaItem->id],
        ])
        ->assertRedirect(route('master-data.ocia-items.index'));

    $this->ociaItem->refresh();

    expect($this->ociaItem->track_stock)->toBeTrue()
        ->and($this->ociaItem->primary_department_id)->toBe($this->kitchen->id)
        ->and($this->kitchen->itemCategories()->where('item_categories.id', $this->ociaCategory->id)->exists())->toBeTrue();
});

test('activated ocia item now appears in opname items for that department', function (): void {
    $this->actingAs($this->admin)->post(route('master-data.ocia-items.bulk-activate-opname'), [
        'department_id' => $this->kitchen->id,
        'ids' => [$this->ociaItem->id],
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

    expect($session->items->pluck('item_id')->all())->toContain($this->ociaItem->id);
});

test('bulk activation survives a subsequent ocia catalog re-sync', function (): void {
    $this->actingAs($this->admin)->post(route('master-data.ocia-items.bulk-activate-opname'), [
        'department_id' => $this->kitchen->id,
        'ids' => [$this->ociaItem->id],
    ]);

    (new OciaCatalogSyncService($this->tenant->id))->sync([[
        'product_id' => 1,
        'product_code' => 'OCIA-ARABICA-001',
        'product_name' => 'Arabica Blend OCIA',
        'is_active' => true,
        'komposisi' => 'BLENDED',
        'jenis_komposisi' => '',
        'unit' => 'kg',
    ]]);

    expect($this->ociaItem->refresh()->track_stock)->toBeTrue()
        ->and($this->ociaItem->primary_department_id)->toBe($this->kitchen->id);
});

test('re-running bulk activate on an already active item changes its department', function (): void {
    $this->actingAs($this->admin)->post(route('master-data.ocia-items.bulk-activate-opname'), [
        'department_id' => $this->kitchen->id,
        'ids' => [$this->ociaItem->id],
    ]);

    expect($this->ociaItem->refresh()->primary_department_id)->toBe($this->kitchen->id);

    $this->actingAs($this->admin)
        ->post(route('master-data.ocia-items.bulk-activate-opname'), [
            'department_id' => $this->bar->id,
            'ids' => [$this->ociaItem->id],
        ])
        ->assertRedirect(route('master-data.ocia-items.index'));

    expect($this->ociaItem->refresh()->track_stock)->toBeTrue()
        ->and($this->ociaItem->refresh()->primary_department_id)->toBe($this->bar->id)
        ->and($this->bar->itemCategories()->where('item_categories.id', $this->ociaCategory->id)->exists())->toBeTrue();
});

test('bulk deactivate clears track_stock and department so item no longer appears in opname', function (): void {
    $this->actingAs($this->admin)->post(route('master-data.ocia-items.bulk-activate-opname'), [
        'department_id' => $this->kitchen->id,
        'ids' => [$this->ociaItem->id],
    ]);
    expect($this->ociaItem->refresh()->track_stock)->toBeTrue();

    $this->actingAs($this->admin)
        ->post(route('master-data.ocia-items.bulk-deactivate-opname'), [
            'ids' => [$this->ociaItem->id],
        ])
        ->assertRedirect(route('master-data.ocia-items.index'));

    $this->ociaItem->refresh();
    expect($this->ociaItem->track_stock)->toBeFalse()
        ->and($this->ociaItem->primary_department_id)->toBeNull();

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

    expect($session->items->pluck('item_id')->all())->not->toContain($this->ociaItem->id);
});

test('non ocia items and items from another tenant are rejected', function (): void {
    $internalItem = Item::query()->where('canonical_sku', 'MKO-AJINOMOTO-500GR')->firstOrFail();

    $this->actingAs($this->admin)
        ->post(route('master-data.ocia-items.bulk-activate-opname'), [
            'department_id' => $this->kitchen->id,
            'ids' => [$internalItem->id],
        ])
        ->assertSessionHasErrors('ids.0');

    expect($internalItem->refresh()->primary_department_id)->toBeNull();
});

test('ocia items index lists only ocia-sourced items and excludes them from the general item list', function (): void {
    $response = $this->actingAs($this->admin)->get(route('master-data.ocia-items.index'));

    $response->assertOk()->assertSee('Arabica Blend OCIA');

    $generalList = $this->actingAs($this->admin)->get(route('master-data.items.index'));
    $generalList->assertOk()->assertDontSee('Arabica Blend OCIA');
});

test('ocia item can be edited for photo and description without changing synced fields', function (): void {
    $response = $this->actingAs($this->admin)->put(route('master-data.ocia-items.update', $this->ociaItem), [
        'description' => 'Catatan khusus outlet',
        'keterangan_pembeda' => 'Batch Oktober',
    ]);

    $response->assertRedirect(route('master-data.ocia-items.index'));

    $this->ociaItem->refresh();
    expect($this->ociaItem->description)->toBe('Catatan khusus outlet')
        ->and($this->ociaItem->keterangan_pembeda)->toBe('Batch Oktober')
        ->and($this->ociaItem->name)->toBe('Arabica Blend OCIA');
});
