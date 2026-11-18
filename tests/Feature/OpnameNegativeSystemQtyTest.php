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

/**
 * Regresi utk bug 2026-11-18: 1 item dengan qty_on_hand OUTLET_DAILY
 * negatif (state sah -- stok sempat ke-oversold sebelum direkonsiliasi,
 * BUKAN data rusak) menggagalkan OpnameService::startSession() utk
 * SELURUH item di departemen itu, karena systemQty() dulu memformat
 * hasil SUM() lewat Decimal::toFixed() yang menolak tanda minus lewat
 * regex. Dilaporkan user lewat Import Opname Bulanan (7/7 tanggal gagal
 * dengan pesan "Decimal value is invalid" yang akhirnya ketahuan
 * menunjuk 1 item: BTE0081 "Baby Chrysant Chinese Tea").
 */
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
        'qty' => '5',
        'performed_by' => $this->admin->id,
    ]);

    // Dorong saldo jadi negatif langsung di stock_balances -- mensimulasikan
    // state akhir "oversold" tanpa perlu mereplikasi urutan mutasi bisnis
    // yang sebenarnya (di luar cakupan test ini).
    StockBalance::query()
        ->where('tenant_id', $this->tenant->id)
        ->where('outlet_id', $this->outlet->id)
        ->where('item_id', $this->item->id)
        ->where('stock_target', StockMutation::TARGET_OUTLET_DAILY)
        ->update(['qty_on_hand' => '-3.500000']);
});

it('creates an opname session without throwing when an item has a negative OUTLET_DAILY balance', function (): void {
    $session = app(OpnameService::class)->startSession([
        'tenant_id' => $this->tenant->id,
        'outlet_id' => $this->outlet->id,
        'department_id' => $this->bar->id,
        'opname_date' => '2026-10-01',
        'type' => OpnameSession::TYPE_DAILY,
    ], $this->admin->id);

    expect($session)->toBeInstanceOf(OpnameSession::class);

    $opnameItem = $session->items()->where('item_id', $this->item->id)->firstOrFail();

    expect($opnameItem->system_qty)->toEqual('-3.500000');
    expect($opnameItem->system_qty_base)->toEqual('-3.500000');
});
