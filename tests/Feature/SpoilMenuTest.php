<?php

use App\Models\User;
use App\Modules\Core\Models\Brand;
use App\Modules\Core\Models\Department;
use App\Modules\Core\Models\Outlet;
use App\Modules\Core\Models\Tenant;
use App\Modules\Operations\Models\SpoilWaste;
use App\Modules\Production\Models\Menu;
use App\Services\SpoilWasteService;
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
    // Seeder: MKO_OUTLET_1 ber-brand MYKOPIO, MKO_OUTLET_2 ber-brand QUALI.
    $this->outlet = Outlet::query()->where('tenant_id', $this->tenant->id)->where('code', 'MKO_OUTLET_1')->firstOrFail();
    $this->bar = Department::query()->where('code', 'BAR')->firstOrFail();

    $this->menu = Menu::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenant->id,
        'brand_id' => Brand::query()->where('code', 'MYKOPIO')->value('id'),
        'code' => 'MK01',
        'name' => 'KOPI SUSU MYKOPIO',
        'is_active' => true,
    ]);

    $this->otherBrandMenu = Menu::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenant->id,
        'brand_id' => Brand::query()->where('code', 'QUALI')->value('id'),
        'code' => 'QL01',
        'name' => 'KOPI SUSU QUALI',
        'is_active' => true,
    ]);

    $this->staff = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'outlet_id' => $this->outlet->id,
        'department_id' => $this->bar->id,
        'status' => 'ACTIVE',
    ]);
    $this->staff->assignRole('STAFF_BAR');
});

/**
 * @return array<string, mixed>
 */
function spoilMenuPayload(object $test, array $row): array
{
    return [
        'outlet_id' => $test->outlet->id,
        'department_id' => $test->bar->id,
        'recorded_date' => now()->toDateString(),
        'items' => [array_merge(['reason_category' => SpoilWaste::REASON_RUSAK], $row)],
    ];
}

test('pencarian di form Catat Spoil ikut menampilkan menu brand outlet, bukan menu brand lain', function (): void {
    $results = $this->actingAs($this->staff)
        ->getJson(route('operations.spoil-wastes.search-items', ['q' => 'kopi susu']))
        ->assertOk()
        ->json();

    $menus = collect($results)->where('type', 'menu');

    expect($menus->pluck('id')->all())->toBe([$this->menu->id])
        ->and($menus->first()['sku'])->toBe('MK01');
});

test('staf (tanpa akses Menu & Resep) bisa mencatat spoil menu: item kosong, satuan porsi', function (): void {
    $this->actingAs($this->staff)
        ->post(route('operations.spoil-wastes.store'), spoilMenuPayload($this, ['menu_id' => $this->menu->id, 'qty_whole' => '2']))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $spoil = SpoilWaste::query()->with('unit')->firstOrFail();

    expect($spoil->item_id)->toBeNull()
        ->and($spoil->menu_id)->toBe($this->menu->id)
        ->and((string) $spoil->qty_in_base_unit)->toBe('2.000000')
        ->and($spoil->unit->code)->toBe('PORSI')
        ->and($spoil->subjectName())->toBe('KOPI SUSU MYKOPIO');
});

test('baris tanpa bahan baku maupun menu ditolak', function (): void {
    $this->actingAs($this->staff)
        ->post(route('operations.spoil-wastes.store'), spoilMenuPayload($this, ['qty_whole' => '2']))
        ->assertSessionHasErrors('items.0.item_id');

    expect(SpoilWaste::query()->count())->toBe(0);
});

test('menu milik brand lain ditolak walau id-nya dikirim manual', function (): void {
    $this->actingAs($this->staff)
        ->post(route('operations.spoil-wastes.store'), spoilMenuPayload($this, ['menu_id' => $this->otherBrandMenu->id, 'qty_whole' => '1']))
        ->assertSessionHasErrors('items.0.menu_id');

    expect(SpoilWaste::query()->count())->toBe(0);
});

test('spoil menu bisa di-approve dan tampil di daftar Spoil & Laporan Spoil', function (): void {
    $spoil = app(SpoilWasteService::class)->record([
        'tenant_id' => $this->tenant->id,
        'outlet_id' => $this->outlet->id,
        'department_id' => $this->bar->id,
        'menu_id' => $this->menu->id,
        'qty_whole' => '3',
        'qty_loose' => '0',
        'recorded_date' => now()->toDateString(),
        'reason_category' => SpoilWaste::REASON_RUSAK,
    ], $this->staff->id);

    app(SpoilWasteService::class)->approve($spoil, $this->admin->id);
    expect($spoil->fresh()->status)->toBe(SpoilWaste::STATUS_APPROVED);

    $this->actingAs($this->admin)
        ->get(route('operations.spoil-wastes.index', ['range' => '7']))
        ->assertOk()
        ->assertSee('KOPI SUSU MYKOPIO');

    // Regresi: laporan dulu INNER JOIN ke items -> baris menu hilang diam-diam.
    $this->actingAs($this->admin)
        ->get(route('laporan.spoil', ['date_from' => now()->toDateString(), 'date_to' => now()->toDateString()]))
        ->assertOk()
        ->assertSee('KOPI SUSU MYKOPIO');
});

test('menu yang sudah punya spoil tidak bisa dihapus permanen, cuma dinonaktifkan', function (): void {
    app(SpoilWasteService::class)->record([
        'tenant_id' => $this->tenant->id,
        'outlet_id' => $this->outlet->id,
        'department_id' => $this->bar->id,
        'menu_id' => $this->menu->id,
        'qty_whole' => '1',
        'recorded_date' => now()->toDateString(),
        'reason_category' => SpoilWaste::REASON_RUSAK,
    ], $this->staff->id);

    $this->actingAs($this->admin)
        ->delete(route('production.menus.destroy', $this->menu))
        ->assertRedirect();

    $menu = Menu::withoutGlobalScopes()->find($this->menu->id);

    expect($menu)->not->toBeNull()
        ->and($menu->is_active)->toBeFalse();
});
