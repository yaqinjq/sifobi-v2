<?php

use App\Models\User;
use App\Modules\Core\Models\Department;
use App\Modules\Core\Models\Outlet;
use App\Modules\Core\Models\Tenant;
use App\Modules\Inventory\Models\Item;
use App\Modules\Inventory\Models\Unit;
use App\Modules\Operations\Models\InventoryCountTemplateItem;
use Database\Seeders\MinimumMasterDataSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Fixtures\InventoryCountSyntheticFixtures;

uses(RefreshDatabase::class);

/**
 * Badge/banner "Perlu Perhatian" di Master Data > Items -- SATU sumber
 * kebenaran yang sama dengan InventoryCountService::resolveLineConfig()
 * (Item::hasValidInventoryRatio(), InventoryCountTemplateItem::configIssues()).
 * Memakai fixture sintetis yang SAMA dengan InventoryCountFullScaleTest.php
 * (Onde-Onde = ratio_unresolved, Simple Syrup dkk = location_unit_mismatch)
 * -- tidak membuat fixture baru.
 */
beforeEach(function (): void {
    $this->seed([RolesAndPermissionsSeeder::class, MinimumMasterDataSeeder::class]);
    $this->tenant = Tenant::query()->where('code', 'MKO')->firstOrFail();
    $this->outlet = Outlet::query()->where('code', 'MKO_OUTLET_1')->firstOrFail();

    $fixtures = (new InventoryCountSyntheticFixtures($this->tenant))->build();
    $this->templates = $fixtures['templates'];
});

function flagTestAdmin(Tenant $tenant, Outlet $outlet): User
{
    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'outlet_id' => $outlet->id,
        'status' => 'ACTIVE',
        'email' => strtolower('admin_flagtest_'.uniqid()).'@sifobi.test',
    ]);
    $user->assignRole('SUPER_ADMIN');

    return $user;
}

test('Item::hasValidInventoryRatio is true only when unit and ratio are both properly configured', function (): void {
    $sameUnit = Unit::query()->where('tenant_id', $this->tenant->id)->first();

    $item = Item::query()->where('tenant_id', $this->tenant->id)->first();

    $item->forceFill(['base_unit_id' => $sameUnit->id, 'inventory_unit_id' => $sameUnit->id, 'inventory_ratio' => 1])->saveQuietly();
    expect($item->refresh()->hasValidInventoryRatio())->toBeFalse('same unit for base and inventory should be invalid');

    $otherUnit = Unit::query()->where('tenant_id', $this->tenant->id)->where('id', '!=', $sameUnit->id)->first();
    $item->forceFill(['inventory_unit_id' => $otherUnit->id, 'inventory_ratio' => 0])->saveQuietly();
    expect($item->refresh()->hasValidInventoryRatio())->toBeFalse('zero ratio should be invalid');

    $item->forceFill(['inventory_ratio' => 12])->saveQuietly();
    expect($item->refresh()->hasValidInventoryRatio())->toBeTrue('distinct units with a positive ratio should be valid');
});

test('the Onde-Onde template item is flagged ratio_unresolved, matching the exact bug that blocks submission', function (): void {
    $wipSauce = $this->templates['KITCHEN_WIP_SAUCE'];
    $ondeTemplateItem = InventoryCountTemplateItem::query()
        ->where('template_id', $wipSauce->id)
        ->whereHas('item', fn ($q) => $q->where('name', 'like', '%Onde%'))
        ->with('item')
        ->firstOrFail();

    expect($ondeTemplateItem->requires_conversion)->toBeTrue()
        ->and($ondeTemplateItem->item->hasValidInventoryRatio())->toBeFalse();

    $issues = $ondeTemplateItem->configIssues($ondeTemplateItem->item);
    expect($issues)->toHaveCount(1)
        ->and($issues[0]['type'])->toBe('ratio_unresolved');
});

test('the 3 BAR items with mismatched location units are flagged location_unit_mismatch', function (): void {
    $barDaily = $this->templates['BAR_DAILY'];
    $crossLocationItems = InventoryCountTemplateItem::query()
        ->where('template_id', $barDaily->id)
        ->whereNotNull('location_units')
        ->with('item')
        ->get();

    expect($crossLocationItems)->toHaveCount(3);

    foreach ($crossLocationItems as $templateItem) {
        $issues = $templateItem->configIssues($templateItem->item);
        $locationIssue = collect($issues)->firstWhere('type', 'location_unit_mismatch');

        expect($locationIssue)->not->toBeNull();
        expect($locationIssue['locations'])->not->toBeEmpty();
    }
});

test('a normal, fully-configured template item has zero config issues', function (): void {
    $sayur = $this->templates['KITCHEN_SAYUR'];
    $templateItem = InventoryCountTemplateItem::query()
        ->where('template_id', $sayur->id)
        ->with('item')
        ->firstOrFail();

    expect($templateItem->configIssues($templateItem->item))->toBe([]);
});

test('Master Data Items list shows the attention badge only for items with real config issues', function (): void {
    config(['app.inventory_simple_enabled' => true]);
    $admin = flagTestAdmin($this->tenant, $this->outlet);

    $wipSauce = $this->templates['KITCHEN_WIP_SAUCE'];
    $ondeItem = InventoryCountTemplateItem::query()
        ->where('template_id', $wipSauce->id)
        ->whereHas('item', fn ($q) => $q->where('name', 'like', '%Onde%'))
        ->with('item')->firstOrFail()->item;

    $sayur = $this->templates['KITCHEN_SAYUR'];
    $cleanItem = InventoryCountTemplateItem::query()
        ->where('template_id', $sayur->id)
        ->with('item')->firstOrFail()->item;

    $response = $this->actingAs($admin)->get(route('master-data.items.index', ['q' => $ondeItem->canonical_sku]));
    $response->assertOk();
    $response->assertSee('Perlu Perhatian');

    $response2 = $this->actingAs($admin)->get(route('master-data.items.index', ['q' => $cleanItem->canonical_sku]));
    $response2->assertOk();
    $response2->assertDontSee('Perlu Perhatian');
});

test('Master Data Items list does zero extra queries for the attention flag when the module flag is off', function (): void {
    config(['app.inventory_simple_enabled' => false]);
    $admin = flagTestAdmin($this->tenant, $this->outlet);

    \Illuminate\Support\Facades\DB::enableQueryLog();
    $this->actingAs($admin)->get(route('master-data.items.index'))->assertOk();
    $queries = \Illuminate\Support\Facades\DB::getQueryLog();
    \Illuminate\Support\Facades\DB::disableQueryLog();

    $templateItemQueries = collect($queries)->filter(
        fn ($q) => str_contains($q['query'], 'inventory_count_template_items')
    );
    expect($templateItemQueries)->toHaveCount(0);
});

test('Item detail page shows the suggested-decision banner for the location-unit-mismatch items', function (): void {
    config(['app.inventory_simple_enabled' => true]);
    $admin = flagTestAdmin($this->tenant, $this->outlet);

    $barDaily = $this->templates['BAR_DAILY'];
    $simpleSyrup = InventoryCountTemplateItem::query()
        ->where('template_id', $barDaily->id)
        ->whereNotNull('location_units')
        ->with('item')->firstOrFail()->item;

    $response = $this->actingAs($admin)->get(route('master-data.items.show', $simpleSyrup));
    $response->assertOk();
    $response->assertSee('Saran keputusan');
    $response->assertSee('pisahkan jadi SKU terpisah per lokasi', false);
});
