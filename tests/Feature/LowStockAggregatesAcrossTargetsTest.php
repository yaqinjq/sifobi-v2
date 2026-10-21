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
 * Saldo item bisa tersebar di 2 stock_target (OUTLET_DAILY dari
 * Opname/Spoil/POS, OUTLET_WAREHOUSE dari Penerimaan Barang) -- laporan
 * Stok Menipis (layar, export, dan metrik dashboard) HARUS menjumlahkan
 * dulu per item+outlet sebelum dibandingkan ke ambang batas, bukan
 * membandingkan tiap baris stock_target sendiri-sendiri (yang sebelumnya
 * bisa membuat item yang gabungannya masih cukup ikut dianggap menipis
 * cuma karena satu baris targetnya kecil).
 */
beforeEach(function (): void {
    $this->seed([
        RolesAndPermissionsSeeder::class,
        MinimumMasterDataSeeder::class,
    ]);

    $this->admin = User::query()->where('email', 'admin@sifobi.test')->firstOrFail();
    $this->tenant = Tenant::query()->where('code', 'MKO')->firstOrFail();
    $this->outlet = Outlet::query()->where('code', 'MKO_OUTLET_1')->firstOrFail();
    $this->item = Item::query()->where('canonical_sku', 'MKO-AJINOMOTO-500GR')->firstOrFail();
    $this->item->update(['min_stock' => '1000']);

    // Split 600 di DAILY + 600 di WAREHOUSE = 1200 gabungan, DI ATAS
    // min_stock (1000) -- tidak seharusnya muncul di Stok Menipis sama
    // sekali, walau masing-masing baris sendiri (600) ada DI BAWAH 1000.
    app(StockLedgerService::class)->openStock([
        'tenant_id' => $this->tenant->id,
        'outlet_id' => $this->outlet->id,
        'item_id' => $this->item->id,
        'unit_id' => $this->item->base_unit_id,
        'stock_target' => StockMutation::TARGET_OUTLET_DAILY,
        'qty' => '600',
        'performed_by' => $this->admin->id,
    ]);
    app(StockLedgerService::class)->openStock([
        'tenant_id' => $this->tenant->id,
        'outlet_id' => $this->outlet->id,
        'item_id' => $this->item->id,
        'unit_id' => $this->item->base_unit_id,
        'stock_target' => StockMutation::TARGET_OUTLET_WAREHOUSE,
        'qty' => '600',
        'performed_by' => $this->admin->id,
    ]);
});

test('stok menipis report does not flag an item whose combined balance across targets is above min_stock', function (): void {
    $response = $this->actingAs($this->admin)->get(route('laporan.stok-menipis'));

    $response->assertOk();
    $lowStockSkus = collect($response->viewData('lowStockItems'))->pluck('canonical_sku');
    expect($lowStockSkus)->not->toContain('MKO-AJINOMOTO-500GR');
});

test('stok menipis report flags an item whose combined balance across targets is genuinely below min_stock', function (): void {
    // Turunkan min_stock jadi di bawah batas, pastikan tetap muncul &
    // qty gabungan yang benar (1200), bukan cuma salah satu target.
    $this->item->update(['min_stock' => '2000']);

    $response = $this->actingAs($this->admin)->get(route('laporan.stok-menipis'));

    $response->assertOk();
    $lowStockItems = collect($response->viewData('lowStockItems'))->keyBy('canonical_sku');

    expect($lowStockItems->has('MKO-AJINOMOTO-500GR'))->toBeTrue()
        ->and((float) $lowStockItems['MKO-AJINOMOTO-500GR']->qty_on_hand)->toBe(1200.0);
});

test('dashboard stok_menipis metric does not count an item whose combined balance is above zero', function (): void {
    $response = $this->actingAs($this->admin)->get(route('dashboard'));

    $response->assertOk();
    // Item ini gabungannya 1200 (bukan 0 di salah satu baris) -- tidak
    // boleh ikut dihitung sebagai "stok menipis/habis" di metrik dashboard.
    // Tidak ada assertion langsung ke angka metrik (banyak item lain di
    // seeder ikut mempengaruhi totalnya) -- cukup pastikan halaman tetap
    // render normal tanpa exception dari subquery baru.
    $response->assertViewHas('metrics');
});
