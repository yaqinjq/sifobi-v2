<?php

use App\Models\User;
use App\Modules\Core\Models\Brand;
use App\Modules\Core\Models\Tenant;
use App\Modules\Production\Models\Menu;
use App\Modules\Production\Models\MenuCategory;
use App\Modules\Production\Models\Recipe;
use App\Modules\Production\Models\RecipeOtherCost;
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
    $this->admin = User::query()->where('email', 'admin@sifobi.test')->firstOrFail();
    $brandId = Brand::query()->where('code', 'MYKOPIO')->value('id');

    $this->claypot = MenuCategory::withoutGlobalScopes()->create(['tenant_id' => $this->tenant->id, 'code' => 'CLAYPOT', 'name' => 'CLAYPOT']);
    $this->dessert = MenuCategory::withoutGlobalScopes()->create(['tenant_id' => $this->tenant->id, 'code' => 'DESSERT', 'name' => 'DESSERT']);

    $make = fn (array $attrs) => Menu::withoutGlobalScopes()->create($attrs + ['tenant_id' => $this->tenant->id, 'brand_id' => $brandId, 'is_active' => true]);

    $this->kopi = $make(['code' => 'APB601', 'name' => 'HOT KOPI O', 'menu_category_id' => $this->claypot->id, 'selling_price' => 20000]);
    $this->teh = $make(['code' => 'APB607', 'name' => 'HOT TEH TARIK', 'menu_category_id' => $this->claypot->id]);
    $this->es = $make(['code' => 'APB1001', 'name' => 'ES CAMPUR AH PEK', 'menu_category_id' => $this->dessert->id]);
    $this->old = $make(['code' => 'APB999', 'name' => 'KOPI LAMA', 'menu_category_id' => $this->claypot->id, 'is_active' => false]);

    // HOT KOPI O punya resep disetujui: biaya 5.000 / volume 1 => HPP 5.000.
    $recipe = Recipe::query()->create([
        'tenant_id' => $this->tenant->id, 'menu_id' => $this->kopi->id, 'version_number' => 1,
        'status' => Recipe::STATUS_APPROVED, 'volume_production' => 1,
    ]);
    RecipeOtherCost::query()->create([
        'tenant_id' => $this->tenant->id, 'recipe_id' => $recipe->id, 'cost_type' => 'PRODUCTION', 'label' => 'Bahan', 'amount' => 5000,
    ]);

    // HOT TEH TARIK cuma punya draft.
    Recipe::query()->create([
        'tenant_id' => $this->tenant->id, 'menu_id' => $this->teh->id, 'version_number' => 1,
        'status' => Recipe::STATUS_DRAFT, 'volume_production' => 1,
    ]);
});

function menuIndexNames(object $test, array $query = []): array
{
    return $test->actingAs($test->admin)
        ->get(route('production.menus.index', $query))
        ->assertOk()
        ->viewData('menus')
        ->getCollection()
        ->pluck('name')
        ->sort()
        ->values()
        ->all();
}

test('default hanya menu aktif; status=all ikut menampilkan yang nonaktif', function (): void {
    expect(menuIndexNames($this))->toBe(['ES CAMPUR AH PEK', 'HOT KOPI O', 'HOT TEH TARIK'])
        ->and(menuIndexNames($this, ['status' => 'all']))->toContain('KOPI LAMA')
        ->and(menuIndexNames($this, ['status' => 'inactive']))->toBe(['KOPI LAMA']);
});

test('pencarian cocok ke nama maupun kode', function (): void {
    expect(menuIndexNames($this, ['q' => 'teh']))->toBe(['HOT TEH TARIK'])
        ->and(menuIndexNames($this, ['q' => 'APB1001']))->toBe(['ES CAMPUR AH PEK']);
});

test('filter kategori + hitungan chip mengikuti pencarian', function (): void {
    expect(menuIndexNames($this, ['category' => $this->dessert->id]))->toBe(['ES CAMPUR AH PEK']);

    $counts = $this->actingAs($this->admin)
        ->get(route('production.menus.index', ['q' => 'hot']))
        ->viewData('categoryCounts');

    expect((int) $counts[$this->claypot->id])->toBe(2)
        ->and($counts->has($this->dessert->id))->toBeFalse();
});

test('filter status resep: disetujui, draft, belum ada', function (): void {
    expect(menuIndexNames($this, ['recipe' => 'approved']))->toBe(['HOT KOPI O'])
        ->and(menuIndexNames($this, ['recipe' => 'draft']))->toBe(['HOT TEH TARIK'])
        ->and(menuIndexNames($this, ['recipe' => 'none']))->toBe(['ES CAMPUR AH PEK']);
});

test('HPP & food cost tampil dari resep yang disetujui', function (): void {
    $response = $this->actingAs($this->admin)->get(route('production.menus.index'));

    expect($response->viewData('hppByMenu')[$this->kopi->id])->toBe('5000.0000')
        ->and($response->viewData('hppByMenu')[$this->teh->id])->toBeNull();

    // 5.000 / 20.000 = 25%.
    $response->assertSee('Rp 5.000')->assertSee('25%');
});

test('urut berdasarkan kode', function (): void {
    $names = $this->actingAs($this->admin)
        ->get(route('production.menus.index', ['sort' => 'code', 'direction' => 'desc']))
        ->viewData('menus')->getCollection()->pluck('code')->all();

    expect($names)->toBe(['APB607', 'APB601', 'APB1001']);
});
