<?php

use App\Models\User;
use App\Modules\Core\Models\Outlet;
use App\Modules\Core\Models\Tenant;
use App\Modules\Inventory\Models\Item;
use App\Modules\Stock\Models\StockMutation;
use App\Services\StockLedgerService;
use Database\Seeders\MinimumMasterDataSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Permintaan pimpinan (lewat laporan user): Spoil & Waste TETAP ikut
 * mengurangi sisa stok/saldo akhir, TAPI jangan digabung ke angka
 * "Keluar"/Out di laporan -- supaya "Out" murni mencerminkan barang
 * keluar operasional (jual/transfer), bukan kerugian/susut.
 */
beforeEach(function (): void {
    $this->seed([
        RolesAndPermissionsSeeder::class,
        MinimumMasterDataSeeder::class,
    ]);

    $this->admin = User::query()->where('email', 'admin@sifobi.test')->firstOrFail();
    $this->tenant = Tenant::query()->where('code', 'MKO')->firstOrFail();
    $this->outlet = Outlet::query()->where('tenant_id', $this->tenant->id)->where('code', 'MKO_OUTLET_1')->firstOrFail();
    $this->item = Item::query()->where('canonical_sku', 'MKO-AJINOMOTO-500GR')->firstOrFail();

    $ledger = app(StockLedgerService::class);

    $ledger->openStock([
        'tenant_id' => $this->tenant->id,
        'outlet_id' => $this->outlet->id,
        'item_id' => $this->item->id,
        'unit_id' => $this->item->base_unit_id,
        'stock_target' => StockMutation::TARGET_OUTLET_DAILY,
        'qty' => '100',
        'performed_by' => $this->admin->id,
    ]);

    $ledger->spoilWaste([
        'tenant_id' => $this->tenant->id,
        'outlet_id' => $this->outlet->id,
        'item_id' => $this->item->id,
        'unit_id' => $this->item->base_unit_id,
        'stock_target' => StockMutation::TARGET_OUTLET_DAILY,
        'qty' => '10',
        'performed_by' => $this->admin->id,
    ]);

    $ledger->posSale([
        'tenant_id' => $this->tenant->id,
        'outlet_id' => $this->outlet->id,
        'item_id' => $this->item->id,
        'unit_id' => $this->item->base_unit_id,
        'stock_target' => StockMutation::TARGET_OUTLET_DAILY,
        'qty' => '20',
        'performed_by' => $this->admin->id,
    ]);
});

test('laporan mutasi: total keluar excludes spoil, spoil shown separately, net unaffected', function (): void {
    $response = $this->actingAs($this->admin)->get(route('laporan.mutasi', [
        'outlet_id' => $this->outlet->id,
        'date_from' => now()->toDateString(),
        'date_to' => now()->toDateString(),
    ]));

    $response->assertOk();
    $summary = $response->viewData('summary');

    expect((float) $summary->total_in)->toBe(100.0)
        ->and((float) $summary->total_out)->toBe(-20.0) // TIDAK termasuk spoil.
        ->and((float) $summary->total_spoil)->toBe(-10.0)
        ->and((float) $summary->net_qty)->toBe(70.0); // 100 - 10 - 20, tidak berubah.
});

test('kartu stok ringkasan: total keluar excludes spoil, saldo akhir still correctly includes it', function (): void {
    $response = $this->actingAs($this->admin)->get(route('laporan.kartu-stok', [
        'outlet_id' => $this->outlet->id,
        'date_from' => now()->toDateString(),
        'date_to' => now()->toDateString(),
    ]));

    $response->assertOk();
    $row = $response->viewData('rows')->firstWhere('item_id', $this->item->id);

    expect((float) $row->total_masuk)->toBe(100.0)
        ->and((float) $row->total_keluar)->toBe(-20.0)
        ->and((float) $row->total_spoil)->toBe(-10.0)
        ->and((float) $row->saldo_akhir)->toBe(70.0);
});

test('kartu stok detail: total_spoil is exposed, saldo akhir still correctly includes it', function (): void {
    $response = $this->actingAs($this->admin)->get(route('laporan.kartu-stok.detail', [
        'item' => $this->item->id,
        'outlet_id' => $this->outlet->id,
        'date_from' => now()->toDateString(),
        'date_to' => now()->toDateString(),
    ]));

    $response->assertOk();

    expect((float) $response->viewData('total_spoil'))->toBe(-10.0)
        ->and((float) $response->viewData('saldo_akhir'))->toBe(70.0);
});
