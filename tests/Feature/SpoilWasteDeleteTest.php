<?php

use App\Models\User;
use App\Modules\Core\Models\Department;
use App\Modules\Core\Models\Outlet;
use App\Modules\Core\Models\Tenant;
use App\Modules\Inventory\Models\Item;
use App\Modules\Operations\Models\SpoilWaste;
use App\Modules\Stock\Models\StockBalance;
use App\Modules\Stock\Models\StockMutation;
use App\Services\SpoilWasteService;
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

function spoilRecordFor(object $test, string $qty = '5'): SpoilWaste
{
    return app(SpoilWasteService::class)->record([
        'tenant_id' => $test->tenant->id,
        'outlet_id' => $test->outlet->id,
        'department_id' => $test->bar->id,
        'item_id' => $test->item->id,
        'unit_id' => $test->item->base_unit_id,
        'qty' => $qty,
        'reason_category' => SpoilWaste::REASON_RUSAK,
        'recorded_date' => '2026-11-14',
        'recorded_at' => '2026-11-14 10:00:00',
    ], $test->admin->id);
}

test('deleting a pending spoil record has no stock impact', function (): void {
    $spoil = spoilRecordFor($this);

    $response = $this->actingAs($this->admin)->delete(route('operations.spoil-wastes.destroy', $spoil));

    $response->assertRedirect(route('operations.spoil-wastes.index'));
    $response->assertSessionHas('success');
    expect(SpoilWaste::query()->find($spoil->id))->toBeNull();
});

test('deleting an approved spoil record reverses the stock deduction', function (): void {
    $spoil = spoilRecordFor($this, '7');
    app(SpoilWasteService::class)->approve($spoil, $this->admin->id);

    $balanceBefore = StockBalance::query()
        ->where('tenant_id', $this->tenant->id)->where('outlet_id', $this->outlet->id)
        ->where('item_id', $this->item->id)->where('stock_target', StockMutation::TARGET_OUTLET_DAILY)
        ->firstOrFail();
    expect((string) $balanceBefore->qty_on_hand)->toBe('93.000000');

    $response = $this->actingAs($this->admin)->delete(route('operations.spoil-wastes.destroy', $spoil));
    $response->assertSessionHas('success');

    expect(SpoilWaste::query()->find($spoil->id))->toBeNull();

    $balanceAfter = StockBalance::query()
        ->where('tenant_id', $this->tenant->id)->where('outlet_id', $this->outlet->id)
        ->where('item_id', $this->item->id)->where('stock_target', StockMutation::TARGET_OUTLET_DAILY)
        ->firstOrFail();
    expect((string) $balanceAfter->qty_on_hand)->toBe('100.000000');
});

test('bulk delete removes multiple records regardless of status', function (): void {
    $pending = spoilRecordFor($this, '2');
    $approved = spoilRecordFor($this, '3');
    app(SpoilWasteService::class)->approve($approved, $this->admin->id);

    $response = $this->actingAs($this->admin)->post(route('operations.spoil-wastes.bulk-delete'), [
        'ids' => [$pending->id, $approved->id],
    ]);

    $response->assertRedirect(route('operations.spoil-wastes.index'));
    $response->assertSessionHas('success');

    expect(SpoilWaste::query()->whereIn('id', [$pending->id, $approved->id])->count())->toBe(0);
});

test('a user without approve_spoil permission cannot delete', function (): void {
    $spoil = spoilRecordFor($this);

    $staff = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'outlet_id' => $this->outlet->id,
        'department_id' => $this->bar->id,
        'status' => 'ACTIVE',
    ]);
    $staff->assignRole('STAFF_BAR');

    $this->actingAs($staff)->delete(route('operations.spoil-wastes.destroy', $spoil))->assertForbidden();
    $this->actingAs($staff)->post(route('operations.spoil-wastes.bulk-delete'), ['ids' => [$spoil->id]])->assertForbidden();

    expect(SpoilWaste::query()->find($spoil->id))->not->toBeNull();
});
