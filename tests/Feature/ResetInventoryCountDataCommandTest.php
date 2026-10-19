<?php

use App\Modules\Core\Models\Department;
use App\Modules\Core\Models\Outlet;
use App\Modules\Core\Models\Tenant;
use App\Modules\Inventory\Models\Item;
use App\Modules\Operations\Models\InventoryCountDocument;
use App\Modules\Operations\Models\InventoryCountTemplate;
use App\Modules\Stock\Models\StockBalance;
use App\Modules\Stock\Models\StockMutation;
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

    $this->tenant = Tenant::query()->where('code', 'MKO')->firstOrFail();
    $this->outlet = Outlet::query()->where('code', 'MKO_OUTLET_1')->firstOrFail();
    $this->bar = Department::query()->where('code', 'BAR')->firstOrFail();

    $barItem = Item::query()->where('canonical_sku', 'MKO-AJINOMOTO-500GR')->firstOrFail();
    $barItem->update(['primary_department_id' => $this->bar->id]);

    app(StockLedgerService::class)->openStock([
        'tenant_id' => $this->tenant->id,
        'outlet_id' => $this->outlet->id,
        'item_id' => $barItem->id,
        'unit_id' => $barItem->base_unit_id,
        'stock_target' => StockMutation::TARGET_OUTLET_DAILY,
        'qty' => '1000',
        'performed_by' => \App\Models\User::query()->where('email', 'admin@sifobi.test')->value('id'),
    ]);
});

test('wipes all inventory count data for the tenant but leaves the stock ledger untouched', function (): void {
    $this->artisan('inventory-count:generate-template', ['department_code' => 'BAR'])->assertSuccessful();

    expect(InventoryCountTemplate::query()->where('tenant_id', $this->tenant->id)->count())->toBeGreaterThan(0);

    $this->artisan('inventory-count:reset', ['--tenant' => 'MKO'])
        ->expectsConfirmation('Lanjutkan hapus semua data di atas?', 'yes')
        ->assertSuccessful();

    expect(InventoryCountTemplate::query()->where('tenant_id', $this->tenant->id)->count())->toBe(0);

    $balance = StockBalance::query()
        ->where('tenant_id', $this->tenant->id)
        ->where('outlet_id', $this->outlet->id)
        ->first();

    expect((string) $balance->qty_on_hand)->toBe('1000.000000'); // ledger tidak tersentuh sama sekali.
});

test('declining the confirmation deletes nothing', function (): void {
    $this->artisan('inventory-count:generate-template', ['department_code' => 'BAR'])->assertSuccessful();
    $before = InventoryCountTemplate::query()->where('tenant_id', $this->tenant->id)->count();

    $this->artisan('inventory-count:reset', ['--tenant' => 'MKO'])
        ->expectsConfirmation('Lanjutkan hapus semua data di atas?', 'no')
        ->assertSuccessful();

    expect(InventoryCountTemplate::query()->where('tenant_id', $this->tenant->id)->count())->toBe($before)
        ->and($before)->toBeGreaterThan(0);
});

test('does nothing and succeeds when there is no data to delete', function (): void {
    $this->artisan('inventory-count:reset', ['--tenant' => 'MKO'])
        ->assertSuccessful();

    expect(InventoryCountTemplate::query()->where('tenant_id', $this->tenant->id)->count())->toBe(0);
});

test('fails cleanly for an unknown tenant code', function (): void {
    $this->artisan('inventory-count:reset', ['--tenant' => 'NOPE'])
        ->assertFailed();
});
