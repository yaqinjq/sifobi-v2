<?php

use App\Models\User;
use App\Modules\Core\Models\Department;
use App\Modules\Core\Models\Outlet;
use App\Modules\Core\Models\Tenant;
use App\Modules\Inventory\Models\Item;
use App\Modules\Operations\Models\SpoilWaste;
use App\Modules\Stock\Models\StockMutation;
use App\Services\SpoilWasteService;
use App\Services\StockLedgerService;
use Database\Seeders\MinimumMasterDataSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed([
        RolesAndPermissionsSeeder::class,
        MinimumMasterDataSeeder::class,
    ]);

    $this->tenant = Tenant::query()->where('code', 'MKO')->firstOrFail();
    $this->outlet = Outlet::query()->where('code', 'MKO_OUTLET_1')->firstOrFail();
    $this->otherOutlet = Outlet::query()->where('code', 'MKO_OUTLET_2')->firstOrFail();
    $this->bar = Department::query()->where('code', 'BAR')->firstOrFail();
    $this->pastry = Department::query()->where('code', 'PASTRY')->firstOrFail();
    $this->service = Department::query()->where('code', 'SERVICE')->firstOrFail();

    $this->barItem = Item::query()->where('canonical_sku', 'MKO-AJINOMOTO-500GR')->firstOrFail();
    $this->barItem->update(['primary_department_id' => $this->bar->id]);

    $this->pastryItem = Item::query()->where('canonical_sku', 'MKO-GULA-PASIR')->firstOrFail();
    $this->pastryItem->update(['primary_department_id' => $this->pastry->id]);

    foreach ([$this->barItem, $this->pastryItem] as $item) {
        app(StockLedgerService::class)->openStock([
            'tenant_id' => $this->tenant->id,
            'outlet_id' => $this->outlet->id,
            'item_id' => $item->id,
            'unit_id' => $item->base_unit_id,
            'stock_target' => StockMutation::TARGET_OUTLET_DAILY,
            'qty' => '1000',
            'performed_by' => User::query()->where('email', 'admin@sifobi.test')->value('id'),
        ]);
    }
});

function spoilUser(string $roleName, ?string $departmentCode, ?Outlet $outlet = null): User
{
    $tenant = Tenant::query()->where('code', 'MKO')->firstOrFail();
    $outlet ??= Outlet::query()->where('code', 'MKO_OUTLET_1')->firstOrFail();
    $department = $departmentCode ? Department::query()->where('code', $departmentCode)->firstOrFail() : null;

    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'outlet_id' => $outlet->id,
        'department_id' => $department?->id,
        'name' => $roleName.' '.($departmentCode ?? 'NODEPT').' User',
        'email' => strtolower($roleName.'_spoil_'.($departmentCode ?? 'nodept').'_'.uniqid()).'@sifobi.test',
        'status' => 'ACTIVE',
    ]);

    $user->assignRole($roleName);

    return $user;
}

function recordSpoilFor(User $user, Outlet $outlet, Department $department, Item $item, string $qtyWhole = '1'): SpoilWaste
{
    return app(SpoilWasteService::class)->record([
        'tenant_id' => $user->tenant_id,
        'outlet_id' => $outlet->id,
        'department_id' => $department->id,
        'item_id' => $item->id,
        'qty_whole' => $qtyWhole,
        'qty_loose' => '0',
        'recorded_date' => now()->toDateString(),
        'reason_category' => SpoilWaste::REASON_RUSAK,
    ], $user->id);
}

test('index only shows spoil waste from the staff own department, excluding legacy null department records', function (): void {
    Storage::fake('public');
    $barStaff = spoilUser('STAFF_BAR', 'BAR');
    $pastryStaff = spoilUser('STAFF_BAR', 'PASTRY');

    $barSpoil = recordSpoilFor($barStaff, $this->outlet, $this->bar, $this->barItem);
    $pastrySpoil = recordSpoilFor($pastryStaff, $this->outlet, $this->pastry, $this->pastryItem);

    $legacySpoil = recordSpoilFor($barStaff, $this->outlet, $this->bar, $this->barItem);
    $legacySpoil->forceFill(['department_id' => null])->saveQuietly();

    $response = $this->actingAs($pastryStaff)->get(route('operations.spoil-wastes.index'));
    $response->assertOk();

    $ids = $response->viewData('spoilWastes')->getCollection()->pluck('id')->all();

    expect($ids)->toContain($pastrySpoil->id)
        ->and($ids)->not->toContain($barSpoil->id)
        ->and($ids)->not->toContain($legacySpoil->id);
});

test('index returns nothing for a user with no department assignment and no approve_spoil permission', function (): void {
    Storage::fake('public');
    // Simulasikan akun yang "belum di-assign dengan benar" -- punya
    // permission record_spoil (lewat role apa pun) tapi tanpa department_id
    // DAN tanpa approve_spoil. Bukan otomatis admin/lihat semua.
    $misconfigured = spoilUser('STAFF_BAR', null);

    $barStaff = spoilUser('STAFF_BAR', 'BAR');
    recordSpoilFor($barStaff, $this->outlet, $this->bar, $this->barItem);

    $response = $this->actingAs($misconfigured)->get(route('operations.spoil-wastes.index'));
    $response->assertOk();

    expect($response->viewData('spoilWastes')->getCollection())->toBeEmpty();
});

test('pic outlet without a fixed department sees spoil across all departments in their own outlet', function (): void {
    Storage::fake('public');
    $pic = spoilUser('PIC_OUTLET', null);
    $barStaff = spoilUser('STAFF_BAR', 'BAR');
    $pastryStaff = spoilUser('STAFF_BAR', 'PASTRY');

    $barSpoil = recordSpoilFor($barStaff, $this->outlet, $this->bar, $this->barItem);
    $pastrySpoil = recordSpoilFor($pastryStaff, $this->outlet, $this->pastry, $this->pastryItem);

    $response = $this->actingAs($pic)->get(route('operations.spoil-wastes.index'));
    $ids = $response->viewData('spoilWastes')->getCollection()->pluck('id')->all();

    expect($ids)->toContain($barSpoil->id)
        ->and($ids)->toContain($pastrySpoil->id);
});

test('opening another departments spoil detail page directly by url is forbidden', function (): void {
    Storage::fake('public');
    $barStaff = spoilUser('STAFF_BAR', 'BAR');
    $pastryStaff = spoilUser('STAFF_BAR', 'PASTRY');

    $barSpoil = recordSpoilFor($barStaff, $this->outlet, $this->bar, $this->barItem);

    $this->actingAs($pastryStaff)
        ->get(route('operations.spoil-wastes.show', $barSpoil))
        ->assertForbidden();
});

test('a legacy spoil record with null department is not visible to department bound staff', function (): void {
    Storage::fake('public');
    $barStaff = spoilUser('STAFF_BAR', 'BAR');

    $legacySpoil = recordSpoilFor($barStaff, $this->outlet, $this->bar, $this->barItem);
    $legacySpoil->forceFill(['department_id' => null])->saveQuietly();

    $this->actingAs($barStaff)
        ->get(route('operations.spoil-wastes.show', $legacySpoil))
        ->assertForbidden();
});

test('opening a spoil detail page from another outlet is forbidden', function (): void {
    Storage::fake('public');
    $barStaff = spoilUser('STAFF_BAR', 'BAR');
    $otherOutletStaff = spoilUser('STAFF_BAR', 'BAR', $this->otherOutlet);

    $barSpoil = recordSpoilFor($barStaff, $this->outlet, $this->bar, $this->barItem);

    $this->actingAs($otherOutletStaff)
        ->get(route('operations.spoil-wastes.show', $barSpoil))
        ->assertForbidden();
});

test('store ignores a forged department_id and outlet_id, always uses the staff own account values', function (): void {
    Storage::fake('public');
    $pastryStaff = spoilUser('STAFF_BAR', 'PASTRY');

    $this->actingAs($pastryStaff)
        ->post(route('operations.spoil-wastes.store'), [
            'outlet_id' => $this->otherOutlet->id,
            'department_id' => $this->bar->id,
            'item_id' => $this->pastryItem->id,
            'qty_whole' => '1',
            'qty_loose' => '0',
            'recorded_date' => now()->toDateString(),
            'reason_category' => SpoilWaste::REASON_RUSAK,
        ])
        ->assertRedirect();

    $spoil = SpoilWaste::query()->latest('id')->first();

    expect((int) $spoil->department_id)->toBe($this->pastry->id)
        ->and((int) $spoil->outlet_id)->toBe($this->outlet->id);
});

test('store rejects an item that does not belong to the staff own department', function (): void {
    Storage::fake('public');
    $barStaff = spoilUser('STAFF_BAR', 'BAR');

    $this->actingAs($barStaff)
        ->post(route('operations.spoil-wastes.store'), [
            'outlet_id' => $this->outlet->id,
            'department_id' => $this->bar->id,
            'item_id' => $this->pastryItem->id,
            'qty_whole' => '1',
            'qty_loose' => '0',
            'recorded_date' => now()->toDateString(),
            'reason_category' => SpoilWaste::REASON_RUSAK,
        ])
        ->assertSessionHasErrors('item_id');

    expect(SpoilWaste::query()->where('item_id', $this->pastryItem->id)->exists())->toBeFalse();
});

test('search items only returns items belonging to the staff own department', function (): void {
    $pastryStaff = spoilUser('STAFF_BAR', 'PASTRY');

    $response = $this->actingAs($pastryStaff)
        ->getJson(route('operations.spoil-wastes.search-items', ['q' => 'MKO']));

    $response->assertOk();
    $ids = collect($response->json())->pluck('id')->all();

    expect($ids)->toContain($this->pastryItem->id)
        ->and($ids)->not->toContain($this->barItem->id);
});

test('approving a spoil from another outlet is forbidden even with approve_spoil permission', function (): void {
    Storage::fake('public');
    $barStaff = spoilUser('STAFF_BAR', 'BAR');
    $picOtherOutlet = spoilUser('PIC_OUTLET', null, $this->otherOutlet);

    $barSpoil = recordSpoilFor($barStaff, $this->outlet, $this->bar, $this->barItem);

    $this->actingAs($picOtherOutlet)
        ->post(route('operations.spoil-wastes.approve', $barSpoil), ['approval_notes' => 'test'])
        ->assertForbidden();

    expect($barSpoil->refresh()->status)->toBe(SpoilWaste::STATUS_PENDING);
});

test('pic outlet can approve spoil from any department within their own outlet', function (): void {
    Storage::fake('public');
    $pastryStaff = spoilUser('STAFF_BAR', 'PASTRY');
    $pic = spoilUser('PIC_OUTLET', null);

    $pastrySpoil = recordSpoilFor($pastryStaff, $this->outlet, $this->pastry, $this->pastryItem);

    $this->actingAs($pic)
        ->post(route('operations.spoil-wastes.approve', $pastrySpoil), ['approval_notes' => 'ok'])
        ->assertRedirect();

    expect($pastrySpoil->refresh()->status)->toBe(SpoilWaste::STATUS_APPROVED);
});

test('rejecting a spoil from another outlet is forbidden', function (): void {
    Storage::fake('public');
    $barStaff = spoilUser('STAFF_BAR', 'BAR');
    $picOtherOutlet = spoilUser('PIC_OUTLET', null, $this->otherOutlet);

    $barSpoil = recordSpoilFor($barStaff, $this->outlet, $this->bar, $this->barItem);

    $this->actingAs($picOtherOutlet)
        ->post(route('operations.spoil-wastes.reject', $barSpoil), ['approval_notes' => 'test'])
        ->assertForbidden();
});

test('spoil report and export force the staff own department regardless of query string', function (): void {
    Storage::fake('public');
    $serviceStaff = spoilUser('STAFF_SERVICE', 'SERVICE');
    // STAFF_SERVICE (dan STAFF_GUDANG) punya view_reports (beda dari
    // STAFF_BAR/KITCHEN) -- pastikan tetap tidak bisa lihat/export
    // departemen lain lewat query string.
    $barStaff = spoilUser('STAFF_BAR', 'BAR');
    recordSpoilFor($barStaff, $this->outlet, $this->bar, $this->barItem);

    $response = $this->actingAs($serviceStaff)
        ->get(route('laporan.spoil', ['department_id' => $this->bar->id]));

    $response->assertOk();
    expect($response->viewData('filters')['department_id'])->toBe($this->service->id);

    $spoils = $response->viewData('spoils');
    expect($spoils->total())->toBe(0);
});

test('cross tenant spoil record is not reachable at all', function (): void {
    Storage::fake('public');
    $otherTenant = Tenant::query()->create(['code' => 'OTHERTEN', 'name' => 'Other Tenant', 'status' => 'ACTIVE']);
    $otherBrand = \App\Modules\Core\Models\Brand::withoutGlobalScopes()->create([
        'tenant_id' => $otherTenant->id, 'code' => 'OTB', 'name' => 'Other Brand', 'status' => 'ACTIVE',
    ]);
    $otherLegalEntity = \App\Modules\Core\Models\LegalEntity::withoutGlobalScopes()->create([
        'tenant_id' => $otherTenant->id, 'code' => 'OTLE', 'name' => 'Other Legal Entity', 'status' => 'ACTIVE',
    ]);
    $otherOutlet = Outlet::withoutGlobalScopes()->create([
        'tenant_id' => $otherTenant->id,
        'brand_id' => $otherBrand->id,
        'legal_entity_id' => $otherLegalEntity->id,
        'code' => 'OTO1',
        'name' => 'Other Outlet',
        'status' => 'ACTIVE',
    ]);

    $foreignSpoil = SpoilWaste::query()->create([
        'tenant_id' => $otherTenant->id,
        'outlet_id' => $otherOutlet->id,
        'department_id' => null,
        'item_id' => $this->barItem->id,
        'unit_id' => $this->barItem->base_unit_id,
        'qty' => '1',
        'qty_in_base_unit' => '1',
        'reason_category' => SpoilWaste::REASON_RUSAK,
        'recorded_date' => now()->toDateString(),
        'recorded_at' => now(),
        'status' => SpoilWaste::STATUS_PENDING,
        'approval_status' => SpoilWaste::STATUS_PENDING,
        'created_by' => 1,
    ]);

    $pic = spoilUser('PIC_OUTLET', null);

    $this->actingAs($pic)
        ->get(route('operations.spoil-wastes.show', $foreignSpoil))
        ->assertNotFound();
});
