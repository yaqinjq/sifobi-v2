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
