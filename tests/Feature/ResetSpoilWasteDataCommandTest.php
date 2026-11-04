<?php

use App\Models\User;
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
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed([
        RolesAndPermissionsSeeder::class,
        MinimumMasterDataSeeder::class,
    ]);

    $this->tenant = Tenant::query()->where('code', 'MKO')->firstOrFail();
    $this->outlet = Outlet::query()->where('tenant_id', $this->tenant->id)->where('code', 'MKO_OUTLET_1')->firstOrFail();
    $this->item = Item::query()->where('tenant_id', $this->tenant->id)->where('canonical_sku', 'MKO-AJINOMOTO-500GR')->firstOrFail();
    $this->admin = User::query()->where('email', 'admin@sifobi.test')->firstOrFail();

    app(StockLedgerService::class)->receiveGoods([
        'tenant_id'    => $this->tenant->id,
        'outlet_id'    => $this->outlet->id,
        'item_id'      => $this->item->id,
        'unit_id'      => $this->item->base_unit_id,
        'qty'          => 100,
        'performed_at' => now()->subWeek(),
    ]);
});

function currentBalance($case): string
{
    return (string) StockBalance::query()
        ->where('tenant_id', $case->tenant->id)
        ->where('outlet_id', $case->outlet->id)
        ->where('item_id', $case->item->id)
        ->where('stock_target', StockMutation::TARGET_OUTLET_DAILY)
        ->value('qty_on_hand');
}

test('spoil yang sudah approved: dihapus, mutasi ikut terhapus, saldo stok dikembalikan', function (): void {
    $spoil = app(SpoilWasteService::class)->record([
        'tenant_id' => $this->tenant->id,
        'outlet_id' => $this->outlet->id,
        'department_id' => null,
        'item_id' => $this->item->id,
        'qty_loose' => 10,
        'reason_category' => SpoilWaste::REASON_RUSAK,
        'recorded_date' => now()->toDateString(),
    ], (int) $this->admin->id);

    app(SpoilWasteService::class)->approve($spoil, (int) $this->admin->id);

    expect(currentBalance($this))->toBe('90.000000');

    Artisan::call('sifobi:reset-spoil-wastes', ['tenant_code' => 'MKO', '--yes' => true]);

    expect(SpoilWaste::query()->whereKey($spoil->id)->exists())->toBeFalse()
        ->and(StockMutation::query()->where('reference_type', SpoilWaste::class)->where('reference_id', $spoil->id)->exists())->toBeFalse()
        ->and(currentBalance($this))->toBe('100.000000');
});

test('spoil approved lalu di-void (reversal): saldo tidak diganggu karena nett sudah 0', function (): void {
    $spoil = app(SpoilWasteService::class)->record([
        'tenant_id' => $this->tenant->id,
        'outlet_id' => $this->outlet->id,
        'department_id' => null,
        'item_id' => $this->item->id,
        'qty_loose' => 15,
        'reason_category' => SpoilWaste::REASON_RUSAK,
        'recorded_date' => now()->toDateString(),
    ], (int) $this->admin->id);

    $spoil = app(SpoilWasteService::class)->approve($spoil, (int) $this->admin->id);
    expect(currentBalance($this))->toBe('85.000000');

    // Simulasikan kasus legacy: mutasi spoil yang sudah ada di-void lewat
    // StockLedgerService langsung (persis pola yang dipakai
    // SpoilWasteService::reject() untuk baris lama yang mutation_id-nya
    // sudah terisi sejak record(), lihat komentar di command-nya).
    app(StockLedgerService::class)->voidMutation($spoil->mutation_id, [
        'reference_type' => SpoilWaste::class,
        'reference_id' => $spoil->id,
        'void_reason' => 'test',
    ]);

    expect(currentBalance($this))->toBe('100.000000');

    Artisan::call('sifobi:reset-spoil-wastes', ['tenant_code' => 'MKO', '--yes' => true]);

    expect(SpoilWaste::query()->whereKey($spoil->id)->exists())->toBeFalse()
        ->and(StockMutation::query()->where('reference_type', SpoilWaste::class)->where('reference_id', $spoil->id)->exists())->toBeFalse()
        ->and(currentBalance($this))->toBe('100.000000');
});

test('spoil masih pending (belum pernah approved): dihapus, saldo stok tidak tersentuh', function (): void {
    $spoil = app(SpoilWasteService::class)->record([
        'tenant_id' => $this->tenant->id,
        'outlet_id' => $this->outlet->id,
        'department_id' => null,
        'item_id' => $this->item->id,
        'qty_loose' => 5,
        'reason_category' => SpoilWaste::REASON_RUSAK,
        'recorded_date' => now()->toDateString(),
    ], (int) $this->admin->id);

    expect(currentBalance($this))->toBe('100.000000');

    Artisan::call('sifobi:reset-spoil-wastes', ['tenant_code' => 'MKO', '--yes' => true]);

    expect(SpoilWaste::query()->whereKey($spoil->id)->exists())->toBeFalse()
        ->and(currentBalance($this))->toBe('100.000000');
});

test('dry-run tidak menghapus atau mengubah apa pun', function (): void {
    $spoil = app(SpoilWasteService::class)->record([
        'tenant_id' => $this->tenant->id,
        'outlet_id' => $this->outlet->id,
        'department_id' => null,
        'item_id' => $this->item->id,
        'qty_loose' => 10,
        'reason_category' => SpoilWaste::REASON_RUSAK,
        'recorded_date' => now()->toDateString(),
    ], (int) $this->admin->id);

    app(SpoilWasteService::class)->approve($spoil, (int) $this->admin->id);

    Artisan::call('sifobi:reset-spoil-wastes', ['tenant_code' => 'MKO', '--dry-run' => true]);

    expect(SpoilWaste::query()->whereKey($spoil->id)->exists())->toBeTrue()
        ->and(currentBalance($this))->toBe('90.000000');
});
