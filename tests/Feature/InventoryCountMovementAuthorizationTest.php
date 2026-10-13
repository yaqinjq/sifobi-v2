<?php

use App\Models\User;
use App\Modules\Core\Models\Department;
use App\Modules\Core\Models\Outlet;
use App\Modules\Core\Models\Tenant;
use App\Modules\Inventory\Models\Item;
use App\Modules\Operations\Models\InventoryCountTemplate;
use App\Modules\Operations\Models\InventoryCountTemplateItem;
use Database\Seeders\MinimumMasterDataSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Temuan UAT C-F1 (docs/technical/uat-inventory-simple-2026-10-13.md):
 * endpoint movement/opening-balance SEBELUM perbaikan ini cuma mengecek
 * item berada di tenant yang sama -- TIDAK mengecek relevansi
 * departemen/template. Dibuktikan lewat request langsung: staf BAR bisa
 * mencatat saldo utk item KITCHEN, bahkan utk item yang tidak terdaftar
 * di template manapun. Test di sini membuktikan perbaikannya.
 */
beforeEach(function (): void {
    $this->seed([RolesAndPermissionsSeeder::class, MinimumMasterDataSeeder::class]);
    $this->tenant = Tenant::query()->where('code', 'MKO')->firstOrFail();
    $this->outlet = Outlet::query()->where('code', 'MKO_OUTLET_1')->firstOrFail();
    $this->bar = Department::query()->where('code', 'BAR')->firstOrFail();
    $this->kitchen = Department::query()->where('code', 'KITCHEN')->firstOrFail();

    $this->barItem = Item::query()->where('canonical_sku', 'MKO-AJINOMOTO-500GR')->firstOrFail();
    $this->kitchenOnlyItem = Item::query()->where('canonical_sku', 'MKO-GULA-PASIR')->firstOrFail();
    $this->unregisteredItem = Item::query()->where('canonical_sku', 'MKO-SUSU-UHT')->firstOrFail();

    $barTemplate = InventoryCountTemplate::query()->create([
        'tenant_id' => $this->tenant->id,
        'department_id' => $this->bar->id,
        'code' => 'BAR_DAILY_AUTH_TEST',
        'name' => 'Bar Harian',
        'frequency' => 'DAILY',
        'locations' => ['GENERIC'],
        'is_generic' => false,
        'is_configured' => true,
    ]);
    InventoryCountTemplateItem::query()->create([
        'tenant_id' => $this->tenant->id,
        'template_id' => $barTemplate->id,
        'item_id' => $this->barItem->id,
        'sort_order' => 1,
        'locations' => ['GENERIC'],
        'requires_conversion' => false,
        'is_active' => true,
    ]);

    $kitchenTemplate = InventoryCountTemplate::query()->create([
        'tenant_id' => $this->tenant->id,
        'department_id' => $this->kitchen->id,
        'code' => 'KITCHEN_DAILY_AUTH_TEST',
        'name' => 'Kitchen Harian',
        'frequency' => 'DAILY',
        'locations' => ['GENERIC'],
        'is_generic' => false,
        'is_configured' => true,
    ]);
    InventoryCountTemplateItem::query()->create([
        'tenant_id' => $this->tenant->id,
        'template_id' => $kitchenTemplate->id,
        'item_id' => $this->kitchenOnlyItem->id,
        'sort_order' => 1,
        'locations' => ['GENERIC'],
        'requires_conversion' => false,
        'is_active' => true,
    ]);
    // $this->unregisteredItem is deliberately NOT attached to any template.

    $this->barStaff = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'outlet_id' => $this->outlet->id,
        'department_id' => $this->bar->id,
        'status' => 'ACTIVE',
    ]);
    $this->barStaff->assignRole('STAFF_BAR');
});

test('BAR staff can record a movement for a BAR-registered item', function (): void {
    $response = $this->actingAs($this->barStaff)
        ->patchJson(route('operations.inventory-count.movements.autosave', $this->barItem), [
            'date' => '2026-10-13',
            'qty_in' => '5',
        ]);

    $response->assertOk();
    $this->assertDatabaseHas('inventory_count_movements', ['item_id' => $this->barItem->id]);
});

test('BAR staff CANNOT record a movement for a KITCHEN-only item', function (): void {
    $response = $this->actingAs($this->barStaff)
        ->patchJson(route('operations.inventory-count.movements.autosave', $this->kitchenOnlyItem), [
            'date' => '2026-10-13',
            'qty_in' => '999',
        ]);

    $response->assertForbidden();
    $this->assertDatabaseMissing('inventory_count_movements', ['item_id' => $this->kitchenOnlyItem->id]);
});

test('BAR staff CANNOT record an opening balance for a KITCHEN-only item', function (): void {
    $response = $this->actingAs($this->barStaff)
        ->postJson(route('operations.inventory-count.movements.opening-balance', $this->kitchenOnlyItem), [
            'week_start' => '2026-10-12',
            'qty' => '100',
        ]);

    $response->assertForbidden();
    $this->assertDatabaseMissing('inventory_count_opening_balances', ['item_id' => $this->kitchenOnlyItem->id]);
});

test('BAR staff CANNOT record a movement for an item not registered to any template', function (): void {
    $response = $this->actingAs($this->barStaff)
        ->patchJson(route('operations.inventory-count.movements.autosave', $this->unregisteredItem), [
            'date' => '2026-10-13',
            'qty_in' => '1',
        ]);

    $response->assertForbidden();
    $this->assertDatabaseMissing('inventory_count_movements', ['item_id' => $this->unregisteredItem->id]);
});

test('forged item id from another tenant is rejected with 404, not 403', function (): void {
    $suffix = uniqid();
    $otherTenant = Tenant::query()->create(['code' => 'OTHERTEN_'.$suffix, 'name' => 'Other Tenant', 'status' => 'ACTIVE']);
    $otherItem = Item::withoutGlobalScopes()->create([
        'tenant_id' => $otherTenant->id,
        'canonical_sku' => 'OTHER-ITEM-'.$suffix,
        'name' => 'Item Tenant Lain',
        'item_type' => 'BAHAN_BAKU',
        'inventory_unit_id' => $this->barItem->inventory_unit_id,
        'base_unit_id' => $this->barItem->base_unit_id,
        'purchase_unit_id' => $this->barItem->purchase_unit_id,
        'inventory_ratio' => '1.000000',
        'purchase_ratio' => '1.000000',
        'yield_pct' => '100.00',
        'standard_cost' => 0,
        'track_stock' => true,
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->barStaff)
        ->patchJson(route('operations.inventory-count.movements.autosave', $otherItem), [
            'date' => '2026-10-13',
            'qty_in' => '1',
        ]);

    $response->assertNotFound();
});
