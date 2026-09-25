<?php

use App\Models\User;
use App\Modules\Core\Models\Department;
use App\Modules\Core\Models\Outlet;
use App\Modules\Core\Models\Tenant;
use App\Modules\Inventory\Models\Item;
use App\Modules\Inventory\Models\ItemCategory;
use App\Modules\Inventory\Models\ItemJenis;
use App\Modules\Inventory\Models\Unit;
use App\Modules\Operations\Models\OpnameSession;
use App\Services\OpnameService;
use Database\Seeders\MinimumMasterDataSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Reproduksi kasus nyata dari pimpinan: Tepung Maizena dipakai di
 * departemen BAR (kategori "Other") dan PASTRY (kategori "Rice & Flour")
 * -- kategori berbeda tergantung departemen yang menghitungnya.
 */
beforeEach(function (): void {
    $this->seed([
        RolesAndPermissionsSeeder::class,
        MinimumMasterDataSeeder::class,
    ]);

    $this->admin = User::query()->where('email', 'admin@sifobi.test')->firstOrFail();
    $this->tenant = Tenant::query()->where('code', 'MKO')->firstOrFail();
    $this->outlet = Outlet::query()->where('tenant_id', $this->tenant->id)->where('code', 'MKO_OUTLET_1')->firstOrFail();
    $this->bar = Department::query()->where('code', 'BAR')->firstOrFail();
    $this->pastry = Department::query()->where('code', 'PASTRY')->firstOrFail();

    // "Other" dan "Rice & Flour" sudah disediakan MinimumMasterDataSeeder
    // (lewat ItemCategorySeeder) -- dipakai ulang di sini, bukan dibuat
    // baru, supaya realistis dengan kategori yang sungguhan ada.
    $this->categoryOther = ItemCategory::withoutGlobalScopes()
        ->where('tenant_id', $this->tenant->id)->where('code', 'OTHER')->firstOrFail();
    $this->categoryRiceFlour = ItemCategory::withoutGlobalScopes()
        ->where('tenant_id', $this->tenant->id)->where('code', 'RICE_FLOUR')->firstOrFail();
    $this->bar->itemCategories()->attach($this->categoryOther->id);
    $this->pastry->itemCategories()->attach($this->categoryRiceFlour->id);

    $gr = Unit::query()->where('tenant_id', $this->tenant->id)->where('code', 'GR')->firstOrFail();

    $this->maizena = Item::query()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Tepung Maizena',
        'canonical_sku' => 'MKO-MAIZENA-001',
        'item_category_id' => $this->categoryRiceFlour->id, // kategori global/default
        'base_unit_id' => $gr->id,
        'inventory_unit_id' => $gr->id,
        'purchase_unit_id' => $gr->id,
        'track_stock' => true,
        'is_active' => true,
    ]);
    $this->maizena->departments()->attach([$this->bar->id, $this->pastry->id]);

    // Wajib ada baris item_outlets untuk outlet ini -- kalau outlet SUDAH
    // punya baris item_outlets lain (dari seeder), tabel itu berlaku
    // sebagai whitelist per outlet di OpnameService::itemsForOpname().
    // Tanpa ini, Maizena tidak akan pernah muncul di sesi Opname manapun
    // walau track_stock & departemennya sudah benar.
    \App\Modules\Inventory\Models\ItemOutlet::query()->create([
        'tenant_id' => $this->tenant->id,
        'item_id' => $this->maizena->id,
        'outlet_id' => $this->outlet->id,
        'is_active' => true,
    ]);
});

test('item resolves category per department when an override exists', function (): void {
    expect($this->maizena->categoryForDepartment($this->pastry->id)->id)->toBe($this->categoryRiceFlour->id)
        ->and($this->maizena->categoryForDepartment($this->bar->id)->id)->toBe($this->categoryRiceFlour->id)
        ->and($this->maizena->categoryForDepartment(null)->id)->toBe($this->categoryRiceFlour->id);

    $this->maizena->departmentCategories()->create([
        'department_id' => $this->bar->id,
        'item_category_id' => $this->categoryOther->id,
    ]);
    $this->maizena->refresh();

    expect($this->maizena->categoryForDepartment($this->bar->id)->id)->toBe($this->categoryOther->id)
        ->and($this->maizena->categoryForDepartment($this->pastry->id)->id)->toBe($this->categoryRiceFlour->id);
});

test('saving item form with override enabled creates a department specific category row', function (): void {
    $jenis = ItemJenis::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->first();
    $gr = Unit::query()->where('tenant_id', $this->tenant->id)->where('code', 'GR')->firstOrFail();

    $this->actingAs($this->admin)
        ->put(route('master-data.items.update', $this->maizena), [
            'canonical_sku' => $this->maizena->canonical_sku,
            'name' => $this->maizena->name,
            'item_type' => 'BAHAN_BAKU',
            'item_jenis_id' => $jenis?->id,
            'item_category_id' => $this->categoryRiceFlour->id,
            'base_unit_id' => $gr->id,
            'inventory_unit_id' => $gr->id,
            'purchase_unit_id' => $gr->id,
            'inventory_ratio' => '1',
            'purchase_ratio' => '1',
            'opname_frequency' => 'DAILY',
            'primary_department_id' => $this->pastry->id,
            'department_ids' => [$this->bar->id, $this->pastry->id],
            'outlet_ids' => [$this->outlet->id],
            'is_active' => '1',
            'department_category_overrides' => [
                $this->bar->id => $this->categoryOther->id,
            ],
        ])
        ->assertRedirect();

    $this->maizena->refresh();

    expect($this->maizena->departmentCategories)->toHaveCount(1)
        ->and($this->maizena->categoryForDepartment($this->bar->id)->id)->toBe($this->categoryOther->id)
        ->and($this->maizena->categoryForDepartment($this->pastry->id)->id)->toBe($this->categoryRiceFlour->id);
});

test('removing an override on save deletes the stored row', function (): void {
    $this->maizena->departmentCategories()->create([
        'department_id' => $this->bar->id,
        'item_category_id' => $this->categoryOther->id,
    ]);

    $jenis = ItemJenis::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->first();
    $gr = Unit::query()->where('tenant_id', $this->tenant->id)->where('code', 'GR')->firstOrFail();

    $this->actingAs($this->admin)
        ->put(route('master-data.items.update', $this->maizena), [
            'canonical_sku' => $this->maizena->canonical_sku,
            'name' => $this->maizena->name,
            'item_type' => 'BAHAN_BAKU',
            'item_jenis_id' => $jenis?->id,
            'item_category_id' => $this->categoryRiceFlour->id,
            'base_unit_id' => $gr->id,
            'inventory_unit_id' => $gr->id,
            'purchase_unit_id' => $gr->id,
            'inventory_ratio' => '1',
            'purchase_ratio' => '1',
            'opname_frequency' => 'DAILY',
            'primary_department_id' => $this->pastry->id,
            'department_ids' => [$this->bar->id, $this->pastry->id],
            'outlet_ids' => [$this->outlet->id],
            'is_active' => '1',
            // department_category_overrides sengaja tidak dikirim -- toggle "off"
        ])
        ->assertRedirect();

    expect($this->maizena->refresh()->departmentCategories)->toHaveCount(0);
});

test('opname session shows the item under the department specific override category', function (): void {
    $this->maizena->departmentCategories()->create([
        'department_id' => $this->bar->id,
        'item_category_id' => $this->categoryOther->id,
    ]);

    $barStaff = User::factory()->create([
        'tenant_id' => $this->tenant->id, 'outlet_id' => $this->outlet->id,
        'department_id' => $this->bar->id, 'status' => 'ACTIVE',
    ]);
    $barStaff->assignRole('STAFF_BAR');

    $pastryStaff = User::factory()->create([
        'tenant_id' => $this->tenant->id, 'outlet_id' => $this->outlet->id,
        'department_id' => $this->pastry->id, 'status' => 'ACTIVE',
    ]);
    $pastryStaff->assignRole('STAFF_BAR');

    $barSession = app(OpnameService::class)->startSession([
        'tenant_id' => $this->tenant->id, 'outlet_id' => $this->outlet->id,
        'department_id' => $this->bar->id, 'type' => OpnameSession::TYPE_DAILY,
        'opname_date' => now()->toDateString(),
    ], $barStaff->id);

    $pastrySession = app(OpnameService::class)->startSession([
        'tenant_id' => $this->tenant->id, 'outlet_id' => $this->outlet->id,
        'department_id' => $this->pastry->id, 'type' => OpnameSession::TYPE_DAILY,
        'opname_date' => now()->toDateString(),
    ], $pastryStaff->id);

    // Filter Opname BAR by "Other" -> Maizena harus muncul (override)
    $this->actingAs($barStaff)
        ->get(route('operations.opname.show', ['session' => $barSession, 'category_id' => $this->categoryOther->id]))
        ->assertOk()
        ->assertViewHas('items', fn ($items) => $items->pluck('item_id')->contains($this->maizena->id));

    // Filter Opname BAR by "Rice & Flour" (kategori global-nya) -> TIDAK muncul, sudah di-override
    $this->actingAs($barStaff)
        ->get(route('operations.opname.show', ['session' => $barSession, 'category_id' => $this->categoryRiceFlour->id]))
        ->assertOk()
        ->assertViewHas('items', fn ($items) => ! $items->pluck('item_id')->contains($this->maizena->id));

    // Filter Opname PASTRY by "Rice & Flour" -> Maizena tetap muncul (tidak ada override utk pastry)
    $this->actingAs($pastryStaff)
        ->get(route('operations.opname.show', ['session' => $pastrySession, 'category_id' => $this->categoryRiceFlour->id]))
        ->assertOk()
        ->assertViewHas('items', fn ($items) => $items->pluck('item_id')->contains($this->maizena->id));

    // Mode "Per Kategori" di sesi BAR mengelompokkan Maizena di bawah "Other"
    $this->actingAs($barStaff)
        ->get(route('operations.opname.show', ['session' => $barSession, 'view_mode' => 'category']))
        ->assertOk()
        ->assertViewHas('groupedItems', function ($groupedItems) {
            $otherGroup = $groupedItems->get('Other');

            return $otherGroup && $otherGroup->pluck('item_id')->contains($this->maizena->id);
        });
});
