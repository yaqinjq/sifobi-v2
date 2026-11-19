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

/**
 * Sejak 2026-11-19, SpoilWasteService::approve() TIDAK LAGI memotong
 * ledger stok sama sekali (lihat komentar di method itu) -- tapi
 * perintah sifobi:reset-spoil-wastes TETAP perlu bisa membersihkan data
 * LEGACY (spoil yang di-approve SEBELUM perubahan itu, yang mutasinya
 * beneran ada di stock_mutations). Helper ini mensimulasikan bentuk data
 * legacy tsb secara langsung (bukan lewat approve() yang sekarang sudah
 * tidak membuat mutasi), supaya jalur reversal command ini tetap teruji.
 */
function simulateLegacyApprovedSpoil($case, SpoilWaste $spoil, string $qtyBaseUnit): SpoilWaste
{
    $mutation = app(StockLedgerService::class)->spoilWaste([
        'tenant_id' => $case->tenant->id,
        'outlet_id' => $case->outlet->id,
        'item_id' => $spoil->item_id,
        'unit_id' => $spoil->item->base_unit_id,
        'stock_target' => StockMutation::TARGET_OUTLET_DAILY,
        'qty_change' => $qtyBaseUnit,
        'reference_type' => SpoilWaste::class,
        'reference_id' => $spoil->id,
        'performed_by' => $case->admin->id,
        'performed_at' => $spoil->recorded_at,
        'notes' => 'Simulasi data legacy utk test.',
    ]);

    $spoil->forceFill([
        'status' => SpoilWaste::STATUS_APPROVED,
        'approval_status' => SpoilWaste::STATUS_APPROVED,
        'approved_by' => $case->admin->id,
        'approved_at' => now(),
        'mutation_id' => $mutation->id,
    ])->save();

    return $spoil->fresh();
}

test('spoil approve sekarang: TIDAK memotong stok sama sekali (kebijakan 2026-11-19)', function (): void {
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

    expect($spoil->fresh()->mutation_id)->toBeNull()
        ->and(currentBalance($this))->toBe('100.000000'); // tidak berubah sama sekali.
});

test('spoil approved LEGACY (data sebelum 2026-11-19): dihapus, mutasi ikut terhapus, saldo stok dikembalikan', function (): void {
    $spoil = app(SpoilWasteService::class)->record([
        'tenant_id' => $this->tenant->id,
        'outlet_id' => $this->outlet->id,
        'department_id' => null,
        'item_id' => $this->item->id,
        'qty_loose' => 10,
        'reason_category' => SpoilWaste::REASON_RUSAK,
        'recorded_date' => now()->toDateString(),
    ], (int) $this->admin->id);

    $spoil = simulateLegacyApprovedSpoil($this, $spoil, '-10.000000');

    expect(currentBalance($this))->toBe('90.000000');

    Artisan::call('sifobi:reset-spoil-wastes', ['tenant_code' => 'MKO', '--yes' => true]);

    expect(SpoilWaste::query()->whereKey($spoil->id)->exists())->toBeFalse()
        ->and(StockMutation::query()->where('reference_type', SpoilWaste::class)->where('reference_id', $spoil->id)->exists())->toBeFalse()
        ->and(currentBalance($this))->toBe('100.000000');
});

test('spoil approved LEGACY lalu di-void (reversal): saldo tidak diganggu karena nett sudah 0', function (): void {
    $spoil = app(SpoilWasteService::class)->record([
        'tenant_id' => $this->tenant->id,
        'outlet_id' => $this->outlet->id,
        'department_id' => null,
        'item_id' => $this->item->id,
        'qty_loose' => 15,
        'reason_category' => SpoilWaste::REASON_RUSAK,
        'recorded_date' => now()->toDateString(),
    ], (int) $this->admin->id);

    $spoil = simulateLegacyApprovedSpoil($this, $spoil, '-15.000000');
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

test('laporan dry-run menghitung jumlah spoil yang pernah menyentuh ledger dengan benar untuk >1 spoil', function (): void {
    $itemB = Item::query()->where('tenant_id', $this->tenant->id)->where('canonical_sku', 'MKO-GULA-PASIR')->firstOrFail();

    app(StockLedgerService::class)->receiveGoods([
        'tenant_id'    => $this->tenant->id,
        'outlet_id'    => $this->outlet->id,
        'item_id'      => $itemB->id,
        'unit_id'      => $itemB->base_unit_id,
        'qty'          => 50,
        'performed_at' => now()->subWeek(),
    ]);

    $spoilA = app(SpoilWasteService::class)->record([
        'tenant_id' => $this->tenant->id,
        'outlet_id' => $this->outlet->id,
        'department_id' => null,
        'item_id' => $this->item->id,
        'qty_loose' => 10,
        'reason_category' => SpoilWaste::REASON_RUSAK,
        'recorded_date' => now()->toDateString(),
    ], (int) $this->admin->id);
    simulateLegacyApprovedSpoil($this, $spoilA, '-10.000000');

    $spoilB = app(SpoilWasteService::class)->record([
        'tenant_id' => $this->tenant->id,
        'outlet_id' => $this->outlet->id,
        'department_id' => null,
        'item_id' => $itemB->id,
        'qty_loose' => 5,
        'reason_category' => SpoilWaste::REASON_RUSAK,
        'recorded_date' => now()->toDateString(),
    ], (int) $this->admin->id);
    simulateLegacyApprovedSpoil($this, $spoilB, '-5.000000');

    Artisan::call('sifobi:reset-spoil-wastes', ['tenant_code' => 'MKO', '--dry-run' => true]);
    $output = Artisan::output();

    expect($output)->toMatch('/Pernah menyentuh ledger stok[^|]*\|\s*2\s*\|/')
        ->and($output)->toMatch('/Baris stock_balances yang akan disesuaikan\s*\|\s*2\s*\|/');
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
        ->and(currentBalance($this))->toBe('100.000000'); // approve() sekarang tidak menyentuh stok.
});
