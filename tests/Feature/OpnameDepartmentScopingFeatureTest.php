<?php

use App\Models\User;
use App\Modules\Core\Models\Department;
use App\Modules\Core\Models\Outlet;
use App\Modules\Core\Models\Tenant;
use App\Modules\Inventory\Models\Item;
use App\Modules\Inventory\Models\ItemCategory;
use App\Modules\Operations\Models\OpnameSession;
use App\Modules\Stock\Models\StockMutation;
use App\Services\OpnameService;
use App\Services\StockLedgerService;
use Database\Seeders\MinimumMasterDataSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed([
        RolesAndPermissionsSeeder::class,
        MinimumMasterDataSeeder::class,
    ]);

    /** @phpstan-ignore-next-line */
    $this->tenant = Tenant::query()->where('code', 'MKO')->firstOrFail();
    /** @phpstan-ignore-next-line */
    $this->outlet = Outlet::query()->where('code', 'MKO_OUTLET_1')->firstOrFail();
    /** @phpstan-ignore-next-line */
    $this->bar = Department::query()->where('code', 'BAR')->firstOrFail();
    /** @phpstan-ignore-next-line */
    $this->kitchen = Department::query()->where('code', 'KITCHEN')->firstOrFail();

    $barItem = Item::query()->where('canonical_sku', 'MKO-AJINOMOTO-500GR')->firstOrFail();
    $barItem->update(['primary_department_id' => $this->bar->id]);

    $kitchenItem = Item::query()->where('canonical_sku', 'MKO-GULA-PASIR')->firstOrFail();
    $kitchenItem->update(['primary_department_id' => $this->kitchen->id]);

    /** @phpstan-ignore-next-line */
    $this->barItem = $barItem;
    /** @phpstan-ignore-next-line */
    $this->kitchenItem = $kitchenItem;

    app(StockLedgerService::class)->openStock([
        'tenant_id' => $this->tenant->id,
        'outlet_id' => $this->outlet->id,
        'item_id' => $barItem->id,
        'unit_id' => $barItem->base_unit_id,
        'stock_target' => StockMutation::TARGET_OUTLET_DAILY,
        'qty' => '1000',
        'performed_by' => User::query()->where('email', 'admin@sifobi.test')->value('id'),
    ]);

    app(StockLedgerService::class)->openStock([
        'tenant_id' => $this->tenant->id,
        'outlet_id' => $this->outlet->id,
        'item_id' => $kitchenItem->id,
        'unit_id' => $kitchenItem->base_unit_id,
        'stock_target' => StockMutation::TARGET_OUTLET_DAILY,
        'qty' => '500',
        'performed_by' => User::query()->where('email', 'admin@sifobi.test')->value('id'),
    ]);
});

function opnameUser(string $roleName, ?string $departmentCode = 'BAR'): User
{
    $tenant = Tenant::query()->where('code', 'MKO')->firstOrFail();
    $outlet = Outlet::query()->where('code', 'MKO_OUTLET_1')->firstOrFail();
    $department = $departmentCode ? Department::query()->where('code', $departmentCode)->firstOrFail() : null;

    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'outlet_id' => $outlet->id,
        'department_id' => $department?->id,
        'name' => $roleName.' '.($departmentCode ?? 'NODEPT').' User',
        'email' => strtolower($roleName.'_'.($departmentCode ?? 'nodept')).'@sifobi.test',
        'status' => 'ACTIVE',
    ]);

    $user->assignRole($roleName);

    return $user;
}

test('department scoped session only includes items belonging to that department', function (): void {
    $staff = opnameUser('STAFF_BAR', 'BAR');

    $session = app(OpnameService::class)->startSession([
        'tenant_id' => $this->tenant->id,
        'outlet_id' => $this->outlet->id,
        'department_id' => $this->bar->id,
        'type' => OpnameSession::TYPE_DAILY,
        'opname_date' => now()->toDateString(),
    ], $staff->id);

    $itemIds = $session->items->pluck('item_id')->all();

    expect($itemIds)->toContain($this->barItem->id)
        ->and($itemIds)->not->toContain($this->kitchenItem->id)
        ->and($session->items->every(fn ($i) => (int) $i->department_id === $this->bar->id))->toBeTrue();
});

test('bar and kitchen can have concurrent draft sessions for the same outlet and date', function (): void {
    $barStaff = opnameUser('STAFF_BAR', 'BAR');
    $kitchenStaff = opnameUser('STAFF_KITCHEN', 'KITCHEN');
    $today = now()->toDateString();

    $barSession = app(OpnameService::class)->startSession([
        'tenant_id' => $this->tenant->id,
        'outlet_id' => $this->outlet->id,
        'department_id' => $this->bar->id,
        'type' => OpnameSession::TYPE_DAILY,
        'opname_date' => $today,
    ], $barStaff->id);

    $kitchenSession = app(OpnameService::class)->startSession([
        'tenant_id' => $this->tenant->id,
        'outlet_id' => $this->outlet->id,
        'department_id' => $this->kitchen->id,
        'type' => OpnameSession::TYPE_DAILY,
        'opname_date' => $today,
    ], $kitchenStaff->id);

    expect($barSession->id)->not->toBe($kitchenSession->id)
        ->and(OpnameSession::query()->whereDate('opname_date', $today)->where('outlet_id', $this->outlet->id)->count())->toBe(2);
});

test('starting a second session for the same department outlet and date is rejected', function (): void {
    $staff = opnameUser('STAFF_BAR', 'BAR');
    $today = now()->toDateString();

    app(OpnameService::class)->startSession([
        'tenant_id' => $this->tenant->id,
        'outlet_id' => $this->outlet->id,
        'department_id' => $this->bar->id,
        'type' => OpnameSession::TYPE_DAILY,
        'opname_date' => $today,
    ], $staff->id);

    app(OpnameService::class)->startSession([
        'tenant_id' => $this->tenant->id,
        'outlet_id' => $this->outlet->id,
        'department_id' => $this->bar->id,
        'type' => OpnameSession::TYPE_DAILY,
        'opname_date' => $today,
    ], $staff->id);
})->throws(ValidationException::class);

test('create form auto-locks outlet and department for a department bound staff', function (): void {
    $staff = opnameUser('STAFF_BAR', 'BAR');

    $this->actingAs($staff)
        ->get(route('operations.opname.create'))
        ->assertOk()
        ->assertViewHas('canChangeOutlet', false)
        ->assertViewHas('canChangeDepartment', false)
        ->assertDontSee('<select name="outlet_id"', false)
        ->assertDontSee('<select name="department_id"', false);
});

test('create form shows department dropdown for a user without a fixed department', function (): void {
    $pic = opnameUser('PIC_OUTLET', null);

    $this->actingAs($pic)
        ->get(route('operations.opname.create'))
        ->assertOk()
        ->assertViewHas('canChangeDepartment', true)
        ->assertSee('<select name="department_id"', false);
});

test('store ignores posted department id and always uses the bound staff department', function (): void {
    $staff = opnameUser('STAFF_BAR', 'BAR');

    $response = $this->actingAs($staff)->post(route('operations.opname.store'), [
        'department_id' => $this->kitchen->id,
    ]);

    $response->assertRedirect();

    $session = OpnameSession::query()->latest('id')->first();
    expect((int) $session->department_id)->toBe($this->bar->id)
        ->and($session->opname_date->toDateString())->toBe(now()->toDateString());
});

test('store requires department selection for a user without a fixed department', function (): void {
    $pic = opnameUser('PIC_OUTLET', null);

    $response = $this->actingAs($pic)->post(route('operations.opname.store'), []);

    $response->assertSessionHasErrors('department_id');
});

test('historical opname can backdate and requires a reason note', function (): void {
    $staff = opnameUser('STAFF_BAR', 'BAR');

    $missingNotes = $this->actingAs($staff)->post(route('operations.opname.store-historical'), [
        'opname_date' => '2026-01-05',
    ]);
    $missingNotes->assertSessionHasErrors('notes');

    $ok = $this->actingAs($staff)->post(route('operations.opname.store-historical'), [
        'opname_date' => '2026-01-05',
        'notes' => 'Opname tanggal ini terlewat karena libur nasional',
    ]);
    $ok->assertRedirect();

    $session = OpnameSession::query()->latest('id')->first();
    expect($session->opname_date->toDateString())->toBe('2026-01-05');
});

test('bulk submit only processes draft sessions owned by the acting outlet', function (): void {
    $staff = opnameUser('STAFF_BAR', 'BAR');
    $today = now()->toDateString();

    $barSession = app(OpnameService::class)->startSession([
        'tenant_id' => $this->tenant->id,
        'outlet_id' => $this->outlet->id,
        'department_id' => $this->bar->id,
        'type' => OpnameSession::TYPE_DAILY,
        'opname_date' => $today,
    ], $staff->id);

    foreach ($barSession->items as $item) {
        app(OpnameService::class)->updateItem($item, '0', '0');
    }

    $this->actingAs($staff)
        ->post(route('operations.opname.bulk-submit'), ['ids' => [$barSession->id]])
        ->assertRedirect(route('operations.opname.index'));

    expect($barSession->refresh()->status)->toBe(OpnameSession::STATUS_SUBMITTED);
});

test('opname show page only offers categories mapped to the session department', function (): void {
    $staff = opnameUser('STAFF_BAR', 'BAR');

    $barCategory = ItemCategory::query()->create([
        'tenant_id' => $this->tenant->id,
        'code' => 'bar_dry_goods',
        'name' => 'BAR DRY GOODS',
        'status' => 'ACTIVE',
        'is_active' => true,
    ]);
    $barSubCategory = ItemCategory::query()->create([
        'tenant_id' => $this->tenant->id,
        'parent_id' => $barCategory->id,
        'code' => 'coffee_tea',
        'name' => 'COFFEE & TEA',
        'status' => 'ACTIVE',
        'is_active' => true,
        'sort_order' => 1,
    ]);
    $this->bar->itemCategories()->attach($barCategory->id);

    $kitchenCategory = ItemCategory::query()->create([
        'tenant_id' => $this->tenant->id,
        'code' => 'kitchen_wip',
        'name' => 'KITCHEN WIP',
        'status' => 'ACTIVE',
        'is_active' => true,
    ]);
    $this->kitchen->itemCategories()->attach($kitchenCategory->id);

    $this->barItem->update(['item_category_id' => $barSubCategory->id]);

    $session = app(OpnameService::class)->startSession([
        'tenant_id' => $this->tenant->id,
        'outlet_id' => $this->outlet->id,
        'department_id' => $this->bar->id,
        'type' => OpnameSession::TYPE_DAILY,
        'opname_date' => now()->toDateString(),
    ], $staff->id);

    $response = $this->actingAs($staff)->get(route('operations.opname.show', $session));

    $response->assertOk()
        ->assertViewHas('categories', function ($categories) use ($barCategory, $barSubCategory, $kitchenCategory) {
            $ids = $categories->pluck('id')->all();

            return in_array($barCategory->id, $ids, true)
                && in_array($barSubCategory->id, $ids, true)
                && ! in_array($kitchenCategory->id, $ids, true);
        });
});

test('opname show page renders for every view mode without error', function (string $viewMode): void {
    $staff = opnameUser('STAFF_BAR', 'BAR');

    $barCategory = ItemCategory::query()->create([
        'tenant_id' => $this->tenant->id,
        'code' => 'bar_dry_goods_vm',
        'name' => 'BAR DRY GOODS',
        'status' => 'ACTIVE',
        'is_active' => true,
    ]);
    $this->barItem->update(['item_category_id' => $barCategory->id]);

    $session = app(OpnameService::class)->startSession([
        'tenant_id' => $this->tenant->id,
        'outlet_id' => $this->outlet->id,
        'department_id' => $this->bar->id,
        'type' => OpnameSession::TYPE_DAILY,
        'opname_date' => now()->toDateString(),
    ], $staff->id);

    $this->actingAs($staff)
        ->get(route('operations.opname.show', ['session' => $session, 'view_mode' => $viewMode]))
        ->assertOk()
        ->assertViewHas('viewMode', $viewMode);
})->with(['card', 'list', 'category', 'zoom']);

test('sort mode form forces show all items and ignores per_page selection', function (): void {
    $staff = opnameUser('STAFF_BAR', 'BAR');

    $session = app(OpnameService::class)->startSession([
        'tenant_id' => $this->tenant->id,
        'outlet_id' => $this->outlet->id,
        'department_id' => $this->bar->id,
        'type' => OpnameSession::TYPE_DAILY,
        'opname_date' => now()->toDateString(),
    ], $staff->id);

    $this->actingAs($staff)
        ->get(route('operations.opname.show', ['session' => $session, 'sort_mode' => 'form', 'per_page' => '20']))
        ->assertOk()
        ->assertViewHas('perPage', 'all')
        ->assertViewHas('paginator', null);
});
