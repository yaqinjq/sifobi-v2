<?php

use App\Models\User;
use App\Modules\Core\Models\Department;
use App\Modules\Core\Models\Outlet;
use App\Modules\Core\Models\Tenant;
use App\Modules\Inventory\Models\Item;
use App\Services\InventoryCountMovementService;
use Database\Seeders\MinimumMasterDataSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

/**
 * Rekap Mingguan: saldo mandiri Inventory Simple, TERISOLASI dari
 * stock_balances/stock_mutations (ledger resmi). Diuji di sini: saldo
 * awal eksplisit, penelusuran mundur antar minggu (BUKAN default ke 0),
 * saldo negatif terdeteksi sebagai error (bukan ditampilkan seolah
 * valid), dan "belum diisi" (null) dibedakan dari "diisi eksplisit 0".
 */
beforeEach(function (): void {
    $this->seed([RolesAndPermissionsSeeder::class, MinimumMasterDataSeeder::class]);
    $this->tenant = Tenant::query()->where('code', 'MKO')->firstOrFail();
    $this->outlet = Outlet::query()->where('code', 'MKO_OUTLET_1')->firstOrFail();
    $this->kitchen = Department::query()->where('code', 'KITCHEN')->firstOrFail();
    $this->item = Item::query()->where('canonical_sku', 'MKO-GULA-PASIR')->firstOrFail();
    $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->service = app(InventoryCountMovementService::class);
});

test('a week with no opening balance anywhere and no prior weeks returns an unresolved error, never a silent zero', function (): void {
    $monday = $this->service->weekStart(Carbon::parse('2026-10-05')); // Senin 2026-10-05.
    $result = $this->service->calculateWeekBalances(
        $this->tenant->id, $this->outlet->id, $this->kitchen->id, $this->item->id, $monday
    );

    expect($result['error'])->not->toBeNull();
    expect($result['days'])->toBe([]); // tidak ada apa-apa untuk dihitung -- bukan daftar berisi nilai 0.
});

test('explicit opening balance plus daily IN/OUT produces a correct running balance across the week', function (): void {
    $monday = $this->service->weekStart(Carbon::parse('2026-10-05'));
    $this->service->recordOpeningBalance(
        $this->tenant->id, $this->outlet->id, $this->kitchen->id, $this->item->id, $monday, '100', $this->user->id
    );

    // Senin: +20 -5 = 115. Selasa: tidak diisi (dihitung 0, tetap 115). Rabu: +0 -30 = 85.
    $this->service->recordMovement($this->tenant->id, $this->outlet->id, $this->kitchen->id, $this->item->id, $monday, '20', '5', $this->user->id);
    $this->service->recordMovement($this->tenant->id, $this->outlet->id, $this->kitchen->id, $this->item->id, $monday->copy()->addDays(2), '0', '30', $this->user->id);

    $result = $this->service->calculateWeekBalances(
        $this->tenant->id, $this->outlet->id, $this->kitchen->id, $this->item->id, $monday
    );

    expect($result['error'])->toBeNull();
    expect($result['days'][0]['balance'])->toBe('115.000000'); // Senin
    expect($result['days'][1]['balance'])->toBe('115.000000'); // Selasa, belum diisi -- saldo tetap (bukan 0).
    expect($result['days'][1]['qty_in'])->toBeNull(); // belum diisi != diisi 0.
    expect($result['days'][2]['balance'])->toBe('85.000000'); // Rabu
});

test('a week without its own opening balance recurses into the previous week automatically', function (): void {
    $week1 = $this->service->weekStart(Carbon::parse('2026-09-28'));
    $week2 = $week1->copy()->addDays(7);

    $this->service->recordOpeningBalance(
        $this->tenant->id, $this->outlet->id, $this->kitchen->id, $this->item->id, $week1, '50', $this->user->id
    );
    // Minggu 1: Senin +10, saldo akhir minggu (Minggu) = 60.
    $this->service->recordMovement($this->tenant->id, $this->outlet->id, $this->kitchen->id, $this->item->id, $week1, '10', null, $this->user->id);

    // Minggu 2 TIDAK punya saldo awal eksplisit -- harus ambil dari akhir minggu 1 (60).
    $result = $this->service->calculateWeekBalances(
        $this->tenant->id, $this->outlet->id, $this->kitchen->id, $this->item->id, $week2
    );

    expect($result['error'])->toBeNull();
    expect($result['base'])->toBe('60.000000');
});

test('a negative balance is flagged as an error and does not silently continue as if valid', function (): void {
    $monday = $this->service->weekStart(Carbon::parse('2026-10-05'));
    $this->service->recordOpeningBalance(
        $this->tenant->id, $this->outlet->id, $this->kitchen->id, $this->item->id, $monday, '10', $this->user->id
    );
    // Senin: OUT 50 > saldo awal 10 -- harusnya negatif, harus diflag error.
    $this->service->recordMovement($this->tenant->id, $this->outlet->id, $this->kitchen->id, $this->item->id, $monday, null, '50', $this->user->id);

    $result = $this->service->calculateWeekBalances(
        $this->tenant->id, $this->outlet->id, $this->kitchen->id, $this->item->id, $monday
    );

    expect($result['error'])->not->toBeNull();
    expect($result['days'][0]['balance'])->toBeNull(); // TIDAK ditampilkan seolah valid.

    // Hari setelahnya juga ikut ter-flag error (saldo mengalir dari hari bermasalah).
    expect($result['days'][1]['error'])->not->toBeNull();
});

test('opening balance and movement autosave never touch stock_balances or stock_mutations', function (): void {
    $balanceCountBefore = \App\Modules\Stock\Models\StockBalance::withoutGlobalScopes()->count();
    $mutationCountBefore = \App\Modules\Stock\Models\StockMutation::withoutGlobalScopes()->count();

    $monday = $this->service->weekStart(Carbon::parse('2026-10-05'));
    $this->service->recordOpeningBalance(
        $this->tenant->id, $this->outlet->id, $this->kitchen->id, $this->item->id, $monday, '100', $this->user->id
    );
    $this->service->recordMovement($this->tenant->id, $this->outlet->id, $this->kitchen->id, $this->item->id, $monday, '20', '5', $this->user->id);

    expect(\App\Modules\Stock\Models\StockBalance::withoutGlobalScopes()->count())->toBe($balanceCountBefore);
    expect(\App\Modules\Stock\Models\StockMutation::withoutGlobalScopes()->count())->toBe($mutationCountBefore);
});
