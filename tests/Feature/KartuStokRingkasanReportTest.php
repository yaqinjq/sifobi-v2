<?php

use App\Models\User;
use App\Modules\Core\Models\Outlet;
use App\Modules\Core\Models\Tenant;
use App\Modules\Inventory\Models\Item;
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
    $this->outlet = Outlet::query()->where('tenant_id', $this->tenant->id)->where('code', 'MKO_OUTLET_1')->firstOrFail();
    $this->item = Item::query()->where('tenant_id', $this->tenant->id)->where('canonical_sku', 'MKO-AJINOMOTO-500GR')->firstOrFail();

    $this->user = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'outlet_id' => $this->outlet->id,
        'status'    => 'ACTIVE',
    ]);
    $this->user->assignRole('PIC_OUTLET');
});

test('halaman Kartu Stok Ringkasan terbuka tanpa error saat belum ada data mutasi sama sekali', function (): void {
    $response = $this->actingAs($this->user)->get(route('laporan.kartu-stok'));

    $response->assertOk();
    expect($response->viewData('rows'))->toBeEmpty();
});

test('Kartu Stok Ringkasan menghitung saldo awal, masuk, keluar, spoil, dan saldo akhir dengan benar', function (): void {
    $ledger = app(StockLedgerService::class);

    $ledger->receiveGoods([
        'tenant_id'    => $this->tenant->id,
        'outlet_id'    => $this->outlet->id,
        'item_id'      => $this->item->id,
        'unit_id'      => $this->item->inventory_unit_id,
        'qty'          => 100,
        'performed_at' => now()->subDays(5),
    ]);

    $ledger->spoilWaste([
        'tenant_id'    => $this->tenant->id,
        'outlet_id'    => $this->outlet->id,
        'item_id'      => $this->item->id,
        'unit_id'      => $this->item->inventory_unit_id,
        'qty'          => 10,
        'performed_at' => now()->subDay(),
    ]);

    $response = $this->actingAs($this->user)->get(route('laporan.kartu-stok', [
        'outlet_id' => $this->outlet->id,
        'date_from' => now()->subDays(2)->toDateString(),
        'date_to'   => now()->toDateString(),
    ]));

    $response->assertOk();

    $rows = $response->viewData('rows');
    $row = $rows->firstWhere('item_id', $this->item->id);

    expect($row)->not->toBeNull()
        ->and((float) $row->saldo_awal)->toBe(100.0)
        ->and((float) $row->total_masuk)->toBe(0.0)
        ->and((float) $row->total_spoil)->toBe(-10.0)
        ->and((float) $row->saldo_akhir)->toBe(90.0);

    // Grid harian: rentang 3 hari (H-2..H), spoil terjadi H-1 -- harus
    // nempel di sel tanggal H-1 saja, bukan nyebar/ketimpuk ke hari lain.
    expect($row->days)->toHaveCount(3);

    $dayMinus2 = $row->days[0];
    $dayMinus1 = $row->days[1];
    $dayToday  = $row->days[2];

    expect($dayMinus2['date'])->toBe(now()->subDays(2)->toDateString())
        ->and((float) $dayMinus2['spoil'])->toBe(0.0)
        ->and((float) $dayMinus2['bal'])->toBe(100.0)
        ->and($dayMinus1['date'])->toBe(now()->subDay()->toDateString())
        ->and((float) $dayMinus1['spoil'])->toBe(-10.0)
        ->and((float) $dayMinus1['bal'])->toBe(90.0)
        ->and($dayToday['date'])->toBe(now()->toDateString())
        ->and((float) $dayToday['spoil'])->toBe(0.0)
        ->and((float) $dayToday['bal'])->toBe(90.0);

    $detailResponse = $this->actingAs($this->user)->get(route('laporan.kartu-stok.detail', [
        'item'      => $this->item->id,
        'outlet_id' => $this->outlet->id,
        'date_from' => now()->subDays(2)->toDateString(),
        'date_to'   => now()->toDateString(),
    ]));

    $detailResponse->assertOk();
    expect((float) $detailResponse->viewData('saldo_akhir'))->toBe(90.0);
});

test('dua transaksi masuk di tanggal berbeda tampil di sel hari yang berbeda, tidak ketimpuk jadi satu', function (): void {
    $ledger = app(StockLedgerService::class);

    $ledger->receiveGoods([
        'tenant_id'    => $this->tenant->id,
        'outlet_id'    => $this->outlet->id,
        'item_id'      => $this->item->id,
        'unit_id'      => $this->item->inventory_unit_id,
        'qty'          => 30,
        'performed_at' => now()->subDays(3),
    ]);

    $ledger->receiveGoods([
        'tenant_id'    => $this->tenant->id,
        'outlet_id'    => $this->outlet->id,
        'item_id'      => $this->item->id,
        'unit_id'      => $this->item->inventory_unit_id,
        'qty'          => 50,
        'performed_at' => now()->subDay(),
    ]);

    $response = $this->actingAs($this->user)->get(route('laporan.kartu-stok', [
        'outlet_id' => $this->outlet->id,
        'date_from' => now()->subDays(3)->toDateString(),
        'date_to'   => now()->toDateString(),
    ]));

    $response->assertOk();

    $row = $response->viewData('rows')->firstWhere('item_id', $this->item->id);

    expect($row->days)->toHaveCount(4);

    $byDate = collect($row->days)->keyBy('date');

    expect((float) $byDate[now()->subDays(3)->toDateString()]['in'])->toBe(30.0)
        ->and((float) $byDate[now()->subDays(3)->toDateString()]['bal'])->toBe(30.0)
        ->and((float) $byDate[now()->subDays(2)->toDateString()]['in'])->toBe(0.0)
        ->and((float) $byDate[now()->subDays(2)->toDateString()]['bal'])->toBe(30.0)
        ->and((float) $byDate[now()->subDay()->toDateString()]['in'])->toBe(50.0)
        ->and((float) $byDate[now()->subDay()->toDateString()]['bal'])->toBe(80.0)
        ->and((float) $byDate[now()->toDateString()]['in'])->toBe(0.0)
        ->and((float) $byDate[now()->toDateString()]['bal'])->toBe(80.0);
});

test('pencarian nama item di Kartu Stok Ringkasan tidak error', function (): void {
    $response = $this->actingAs($this->user)->get(route('laporan.kartu-stok', [
        'outlet_id' => $this->outlet->id,
        'q'         => 'Ajinomoto',
    ]));

    $response->assertOk();
});
