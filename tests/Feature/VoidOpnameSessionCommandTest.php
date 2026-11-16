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
use Illuminate\Support\Facades\Artisan;

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

function currentOpnameTestBalance(object $test): string
{
    return (string) StockBalance::query()
        ->where('tenant_id', $test->tenant->id)->where('outlet_id', $test->outlet->id)
        ->where('item_id', $test->item->id)->where('stock_target', StockMutation::TARGET_OUTLET_DAILY)
        ->value('qty_on_hand');
}

test('a processed session: is deleted and the stock balance is reversed back', function (): void {
    $session = app(OpnameService::class)->startSession([
        'tenant_id' => $this->tenant->id,
        'outlet_id' => $this->outlet->id,
        'department_id' => $this->bar->id,
        'opname_date' => '2026-11-30',
        'type' => OpnameSession::TYPE_DAILY,
    ], $this->admin->id);
    $session->fresh(['items'])->items->each(fn ($i) => app(OpnameService::class)->updateItem($i, '0', '30'));
    app(OpnameService::class)->submit($session->fresh(['items']), $this->admin->id);
    app(OpnameService::class)->approve($session->fresh(['items']), $this->admin->id);

    expect(currentOpnameTestBalance($this))->toBe('30.000000');

    $exitCode = Artisan::call('sifobi:void-opname-session', [
        'tenant_code' => 'MKO',
        'outlet' => $this->outlet->code,
        'department' => $this->bar->code,
        'date' => '2026-11-30',
        '--yes' => true,
    ]);

    expect($exitCode)->toBe(0);
    expect(OpnameSession::query()->whereKey($session->id)->exists())->toBeFalse();
    expect(currentOpnameTestBalance($this))->toBe('100.000000');
});

test('dry run does not delete or adjust anything', function (): void {
    $session = app(OpnameService::class)->startSession([
        'tenant_id' => $this->tenant->id,
        'outlet_id' => $this->outlet->id,
        'department_id' => $this->bar->id,
        'opname_date' => '2026-11-30',
        'type' => OpnameSession::TYPE_DAILY,
    ], $this->admin->id);
    $session->fresh(['items'])->items->each(fn ($i) => app(OpnameService::class)->updateItem($i, '0', '30'));
    app(OpnameService::class)->submit($session->fresh(['items']), $this->admin->id);
    app(OpnameService::class)->approve($session->fresh(['items']), $this->admin->id);

    Artisan::call('sifobi:void-opname-session', [
        'tenant_code' => 'MKO',
        'outlet' => $this->outlet->code,
        'department' => $this->bar->code,
        'date' => '2026-11-30',
        '--dry-run' => true,
    ]);

    expect(OpnameSession::query()->whereKey($session->id)->exists())->toBeTrue();
    expect(currentOpnameTestBalance($this))->toBe('30.000000');
});

test('outlet matched by name also works', function (): void {
    $session = app(OpnameService::class)->startSession([
        'tenant_id' => $this->tenant->id,
        'outlet_id' => $this->outlet->id,
        'department_id' => $this->bar->id,
        'opname_date' => '2026-11-30',
        'type' => OpnameSession::TYPE_DAILY,
    ], $this->admin->id);

    $exitCode = Artisan::call('sifobi:void-opname-session', [
        'tenant_code' => 'MKO',
        'outlet' => $this->outlet->name,
        'department' => $this->bar->name,
        'date' => '2026-11-30',
        '--yes' => true,
    ]);

    expect($exitCode)->toBe(0);
    expect(OpnameSession::query()->whereKey($session->id)->exists())->toBeFalse();
});

test('returns a clear failure when no session matches', function (): void {
    $exitCode = Artisan::call('sifobi:void-opname-session', [
        'tenant_code' => 'MKO',
        'outlet' => $this->outlet->code,
        'department' => $this->bar->code,
        'date' => '2026-12-25',
        '--yes' => true,
    ]);

    expect($exitCode)->toBe(1);
});
