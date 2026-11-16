<?php

use App\Models\User;
use App\Modules\Core\Models\Department;
use App\Modules\Core\Models\Outlet;
use App\Modules\Core\Models\Tenant;
use App\Modules\Inventory\Models\Item;
use App\Modules\Operations\Models\OpnameSession;
use App\Modules\Stock\Models\StockBalance;
use App\Modules\Stock\Models\StockMutation;
use App\Services\OpnameService;
use App\Services\StockLedgerService;
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
    $this->outlet = Outlet::query()->where('tenant_id', $this->tenant->id)->where('code', 'MKO_OUTLET_1')->firstOrFail();
    $this->bar = Department::query()->where('code', 'BAR')->firstOrFail();
    $this->item = Item::query()->where('canonical_sku', 'MKO-AJINOMOTO-500GR')->firstOrFail();
    $this->item->update(['primary_department_id' => $this->bar->id]);

    app(StockLedgerService::class)->openStock([
        'tenant_id' => $this->tenant->id,
        'outlet_id' => $this->outlet->id,
        'item_id' => $this->item->id,
        'unit_id' => $this->item->base_unit_id,
        'stock_target' => StockMutation::TARGET_OUTLET_DAILY,
        'qty' => '100',
        'performed_by' => $this->admin->id,
    ]);
});

function opnameDeleteTestBalance(object $test): string
{
    return (string) StockBalance::query()
        ->where('tenant_id', $test->tenant->id)->where('outlet_id', $test->outlet->id)
        ->where('item_id', $test->item->id)->where('stock_target', StockMutation::TARGET_OUTLET_DAILY)
        ->value('qty_on_hand');
}

function opnameDeleteTestSession(object $test, string $date): OpnameSession
{
    return app(OpnameService::class)->startSession([
        'tenant_id' => $test->tenant->id,
        'outlet_id' => $test->outlet->id,
        'department_id' => $test->bar->id,
        'opname_date' => $date,
        'type' => OpnameSession::TYPE_DAILY,
    ], $test->admin->id);
}

test('deleting a draft session has no stock impact', function (): void {
    $session = opnameDeleteTestSession($this, '2026-11-01');

    $response = $this->actingAs($this->admin)->delete(route('operations.opname.destroy', $session));

    $response->assertRedirect(route('operations.opname.index'));
    $response->assertSessionHas('success');
    expect(OpnameSession::query()->find($session->id))->toBeNull();
});

test('deleting a processed session reverses the stock adjustment', function (): void {
    $session = opnameDeleteTestSession($this, '2026-11-02');
    $session->fresh(['items'])->items->each(fn ($i) => app(OpnameService::class)->updateItem($i, '0', '30'));
    app(OpnameService::class)->submit($session->fresh(['items']), $this->admin->id);
    app(OpnameService::class)->approve($session->fresh(['items']), $this->admin->id);

    expect(opnameDeleteTestBalance($this))->toBe('30.000000');

    $response = $this->actingAs($this->admin)->delete(route('operations.opname.destroy', $session));
    $response->assertSessionHas('success');

    expect(OpnameSession::query()->find($session->id))->toBeNull();
    expect(opnameDeleteTestBalance($this))->toBe('100.000000');
});

test('bulk delete removes multiple sessions regardless of status', function (): void {
    $draft = opnameDeleteTestSession($this, '2026-11-03');
    $processed = opnameDeleteTestSession($this, '2026-11-04');
    $processed->fresh(['items'])->items->each(fn ($i) => app(OpnameService::class)->updateItem($i, '0', '10'));
    app(OpnameService::class)->submit($processed->fresh(['items']), $this->admin->id);
    app(OpnameService::class)->approve($processed->fresh(['items']), $this->admin->id);

    $response = $this->actingAs($this->admin)->post(route('operations.opname.bulk-delete'), [
        'ids' => [$draft->id, $processed->id],
    ]);

    $response->assertRedirect(route('operations.opname.index'));
    $response->assertSessionHas('success');

    expect(OpnameSession::query()->whereIn('id', [$draft->id, $processed->id])->count())->toBe(0);
    expect(opnameDeleteTestBalance($this))->toBe('100.000000');
});

test('a user without approve_opname permission cannot delete', function (): void {
    $session = opnameDeleteTestSession($this, '2026-11-05');

    $staff = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'outlet_id' => $this->outlet->id,
        'department_id' => $this->bar->id,
        'status' => 'ACTIVE',
    ]);
    $staff->assignRole('STAFF_BAR');

    $this->actingAs($staff)->delete(route('operations.opname.destroy', $session))->assertForbidden();
    $this->actingAs($staff)->post(route('operations.opname.bulk-delete'), ['ids' => [$session->id]])->assertForbidden();

    expect(OpnameSession::query()->find($session->id))->not->toBeNull();
});

test('a user cannot delete a session belonging to another outlet', function (): void {
    $otherOutlet = Outlet::query()->where('tenant_id', $this->tenant->id)->where('code', 'MKO_OUTLET_2')->firstOrFail();
    $session = app(OpnameService::class)->startSession([
        'tenant_id' => $this->tenant->id,
        'outlet_id' => $otherOutlet->id,
        'department_id' => $this->bar->id,
        'opname_date' => '2026-11-06',
        'type' => OpnameSession::TYPE_DAILY,
    ], $this->admin->id);

    $manager = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'outlet_id' => $this->outlet->id,
        'department_id' => $this->bar->id,
        'status' => 'ACTIVE',
    ]);
    $manager->assignRole('MANAGER_AREA');

    $this->actingAs($manager)->delete(route('operations.opname.destroy', $session))->assertForbidden();

    expect(OpnameSession::query()->find($session->id))->not->toBeNull();
});
