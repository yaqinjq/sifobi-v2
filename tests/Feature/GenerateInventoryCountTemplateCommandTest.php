<?php

use App\Modules\Core\Models\Department;
use App\Modules\Core\Models\Tenant;
use App\Modules\Inventory\Models\Item;
use App\Modules\Operations\Models\InventoryCountTemplate;
use Database\Seeders\MinimumMasterDataSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed([
        RolesAndPermissionsSeeder::class,
        MinimumMasterDataSeeder::class,
    ]);

    $this->tenant = Tenant::query()->where('code', 'MKO')->firstOrFail();
    $this->bar = Department::query()->where('code', 'BAR')->firstOrFail();

    $barItem = Item::query()->where('canonical_sku', 'MKO-AJINOMOTO-500GR')->firstOrFail();
    $barItem->update(['primary_department_id' => $this->bar->id]);
    $this->barItem = $barItem;
});

test('generates a template using only real, already-assigned items with no conversion config', function (): void {
    $this->artisan('inventory-count:generate-template', ['department_code' => 'BAR'])
        ->assertSuccessful();

    $template = InventoryCountTemplate::query()
        ->where('tenant_id', $this->tenant->id)
        ->where('department_id', $this->bar->id)
        ->firstOrFail();

    expect($template->status)->toBe(InventoryCountTemplate::STATUS_ACTIVE)
        ->and($template->is_configured)->toBeTrue()
        ->and($template->locations)->toBe(['Gudang']);

    $templateItem = $template->items()->where('item_id', $this->barItem->id)->first();

    expect($templateItem)->not->toBeNull()
        ->and($templateItem->requires_conversion)->toBeFalse()
        ->and($templateItem->locations)->toBe(['Gudang']);
});

test('is idempotent -- running twice does not duplicate the template or its items', function (): void {
    $this->artisan('inventory-count:generate-template', ['department_code' => 'BAR'])->assertSuccessful();
    $firstCount = InventoryCountTemplate::query()->where('department_id', $this->bar->id)->count();
    $firstItemCount = InventoryCountTemplate::query()->where('department_id', $this->bar->id)->firstOrFail()->items()->count();

    $this->artisan('inventory-count:generate-template', ['department_code' => 'BAR'])->assertSuccessful();
    $secondCount = InventoryCountTemplate::query()->where('department_id', $this->bar->id)->count();
    $secondItemCount = InventoryCountTemplate::query()->where('department_id', $this->bar->id)->firstOrFail()->items()->count();

    expect($secondCount)->toBe($firstCount)
        ->and($secondItemCount)->toBe($firstItemCount);
});

test('a newly-added department item is picked up on a second run without duplicating existing items', function (): void {
    $this->artisan('inventory-count:generate-template', ['department_code' => 'BAR'])->assertSuccessful();
    $firstItemCount = InventoryCountTemplate::query()->where('department_id', $this->bar->id)->firstOrFail()->items()->count();

    $susuItem = Item::query()->where('canonical_sku', 'MKO-SUSU-UHT')->firstOrFail();
    $susuItem->update(['primary_department_id' => $this->bar->id]);

    $this->artisan('inventory-count:generate-template', ['department_code' => 'BAR'])->assertSuccessful();
    $template = InventoryCountTemplate::query()->where('department_id', $this->bar->id)->firstOrFail();

    expect($template->items()->count())->toBe($firstItemCount + 1)
        ->and($template->items()->where('item_id', $susuItem->id)->exists())->toBeTrue();
});

test('fails cleanly for an unknown department code', function (): void {
    $this->artisan('inventory-count:generate-template', ['department_code' => 'NOPE'])
        ->assertFailed();
});

test('fails cleanly when the department has no eligible items', function (): void {
    $empty = Department::query()->create([
        'tenant_id' => $this->tenant->id,
        'code' => 'EMPTYDEPT',
        'name' => 'Empty Dept',
        'is_operational' => true,
        'status' => 'ACTIVE',
    ]);

    $this->artisan('inventory-count:generate-template', ['department_code' => 'EMPTYDEPT'])
        ->assertFailed();

    expect(InventoryCountTemplate::query()->where('department_id', $empty->id)->exists())->toBeFalse();
});
