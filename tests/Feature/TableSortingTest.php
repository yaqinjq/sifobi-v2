<?php

use App\Models\User;
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

test('master data items index sorts by name descending via ?sort=name&direction=desc (refactored onto the shared sortable-th component)', function (): void {
    $response = $this->actingAs($this->admin)
        ->get(route('master-data.items.index', ['sort' => 'name', 'direction' => 'desc']));

    $response->assertOk();

    $names = Item::query()
        ->where('tenant_id', $this->admin->tenant_id)
        ->orderByDesc('name')
        ->pluck('name')
        ->all();

    $html = $response->getContent();
    $positions = array_map(fn ($name) => strpos($html, e($name)), $names);

    expect($positions)->not->toContain(false);
    expect($positions)->toBe(collect($positions)->sort()->values()->all());
});

test('an unknown sort column falls back to the default instead of erroring', function (): void {
    $this->actingAs($this->admin)
        ->get(route('master-data.items.index', ['sort' => 'tenant_id; drop table items;', 'direction' => 'asc']))
        ->assertOk();
});

test('every column wired to the sortable-th component accepts sort+direction without erroring', function (): void {
    $cases = [
        ['route' => 'settings.brands.index', 'columns' => ['code', 'name', 'status']],
        ['route' => 'settings.outlets.index', 'columns' => ['code', 'name', 'status']],
        ['route' => 'master-data.units.index', 'columns' => ['code', 'name', 'abbreviation']],
        ['route' => 'master-data.wipro-items.index', 'columns' => ['canonical_sku', 'name', 'is_active']],
        ['route' => 'settings.users.index', 'columns' => ['name', 'status', 'last_login_at']],
    ];

    foreach ($cases as $case) {
        foreach ($case['columns'] as $column) {
            foreach (['asc', 'desc'] as $direction) {
                $this->actingAs($this->admin)
                    ->get(route($case['route'], ['sort' => $column, 'direction' => $direction]))
                    ->assertOk();
            }
        }
    }
});
