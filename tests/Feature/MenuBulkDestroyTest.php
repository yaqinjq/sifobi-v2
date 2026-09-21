<?php

use App\Models\User;
use App\Modules\Core\Models\Brand;
use App\Modules\Core\Models\Tenant;
use App\Modules\Production\Models\Menu;
use App\Modules\Production\Models\Recipe;
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
    $this->brand = Brand::query()->where('tenant_id', $this->tenant->id)->where('code', 'MYKOPIO')->firstOrFail();
});

test('bulk destroy hard deletes menus without recipes and deactivates menus with recipes', function (): void {
    $menuNoRecipe = Menu::query()->create([
        'tenant_id' => $this->tenant->id,
        'brand_id' => $this->brand->id,
        'name' => 'Menu Tanpa Resep',
        'is_active' => true,
    ]);

    $menuWithRecipe = Menu::query()->create([
        'tenant_id' => $this->tenant->id,
        'brand_id' => $this->brand->id,
        'name' => 'Menu Dengan Resep',
        'is_active' => true,
    ]);

    Recipe::query()->create([
        'tenant_id' => $this->tenant->id,
        'menu_id' => $menuWithRecipe->id,
        'version_number' => 1,
        'status' => Recipe::STATUS_DRAFT,
        'volume_production' => 1,
    ]);

    $this->actingAs($this->admin)
        ->post(route('production.menus.bulk-destroy'), [
            'ids' => [$menuNoRecipe->id, $menuWithRecipe->id],
        ])
        ->assertRedirect(route('production.menus.index'));

    $this->assertDatabaseMissing('menus', ['id' => $menuNoRecipe->id]);

    expect($menuWithRecipe->refresh()->is_active)->toBeFalse();
});
