<?php

use App\Modules\Core\Models\Department;
use App\Modules\Core\Models\Outlet;
use App\Modules\Core\Models\Tenant;
use App\Modules\Inventory\Models\Item;
use App\Modules\Stock\Models\StockBalance;
use App\Modules\Stock\Models\StockMutation;
use App\Services\OpnameService;
use App\Services\StockLedgerService;
use Database\Seeders\MinimumMasterDataSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Laporan user nyata di production: sesi Opname yang dibuat tanggal 4
 * Oktober baru sempat di-approve tanggal 19 Oktober (15 hari kemudian).
 * Dalam rentang itu ada transaksi lain (spoil) pada item yang sama --
 * approve() gagal dengan "Stock balance is not sufficient for this
 * mutation." (pesan Inggris mentah, membingungkan staf), padahal hasil
 * hitung fisiknya sendiri valid. Akar masalah: system_qty_base yang
 * dipakai utk hitung selisih itu DIBEKUKAN sejak sesi dibuat, bukan
 * saldo live saat benar-benar diposting.
 */
beforeEach(function (): void {
    $this->seed([
        RolesAndPermissionsSeeder::class,
        MinimumMasterDataSeeder::class,
    ]);

    $this->admin = \App\Models\User::query()->where('email', 'admin@sifobi.test')->firstOrFail();
    $this->tenant = Tenant::query()->where('code', 'MKO')->firstOrFail();
    $this->outlet = Outlet::query()->where('tenant_id', $this->tenant->id)->where('code', 'MKO_OUTLET_1')->firstOrFail();
    $this->bar = Department::query()->where('tenant_id', $this->tenant->id)->where('code', 'BAR')->firstOrFail();

    $this->item = Item::query()->where('canonical_sku', 'MKO-AJINOMOTO-500GR')->firstOrFail();
    $this->item->update(['primary_department_id' => $this->bar->id]);

    app(StockLedgerService::class)->openStock([
        'tenant_id' => $this->tenant->id,
        'outlet_id' => $this->outlet->id,
        'item_id' => $this->item->id,
        'unit_id' => $this->item->base_unit_id,
        'stock_target' => StockMutation::TARGET_OUTLET_DAILY,
        'qty' => '1000',
        'performed_by' => $this->admin->id,
    ]);
});

test('approving a session long after other mutations happened still succeeds, using live balance not the frozen snapshot', function (): void {
    $session = app(OpnameService::class)->startSession([
        'tenant_id' => $this->tenant->id,
        'outlet_id' => $this->outlet->id,
        'department_id' => $this->bar->id,
        'type' => \App\Modules\Operations\Models\OpnameSession::TYPE_DAILY,
        'opname_date' => now()->subDays(15)->toDateString(),
    ], $this->admin->id);

    $row = $session->items->firstWhere('item_id', $this->item->id);
    app(OpnameService::class)->updateItem($row, '0', '700'); // fisik dihitung: 700.

    // Sesi disubmit, lalu "menunggu approval" beberapa hari -- selama itu
    // terjadi mutasi LAIN pada item yang sama (mis. Spoil & Waste),
    // membuat system_qty_base yang dibekukan (1000) jadi basi.
    app(OpnameService::class)->submit($session->fresh(), $this->admin->id);

    app(StockLedgerService::class)->spoilWaste([
        'tenant_id' => $this->tenant->id,
        'outlet_id' => $this->outlet->id,
        'item_id' => $this->item->id,
        'unit_id' => $this->item->base_unit_id,
        'stock_target' => StockMutation::TARGET_OUTLET_DAILY,
        'qty' => '750', // saldo real sekarang: 1000 - 750 = 250 (di bawah "fisik" 700).
        'performed_by' => $this->admin->id,
    ]);

    // Baseline lama (1000) vs fisik (700) akan hasilkan qty_change -300,
    // yang kalau diterapkan ke saldo real SEKARANG (250) jadi -50 --
    // persis bug yang dilaporkan user. Approve TIDAK BOLEH gagal.
    app(OpnameService::class)->approve($session->fresh(), $this->admin->id);

    $balance = StockBalance::query()
        ->where('tenant_id', $this->tenant->id)
        ->where('outlet_id', $this->outlet->id)
        ->where('item_id', $this->item->id)
        ->first();

    // Saldo akhir HARUS persis sama dengan hasil hitung fisik (700) --
    // itulah tujuan sebenarnya dari approve Opname: menyamakan stok
    // sistem dengan kondisi fisik yang baru saja dihitung, terlepas dari
    // apa pun yang terjadi di antara submit dan approve.
    expect((string) $balance->qty_on_hand)->toBe('700.000000');
});

test('if the live balance genuinely cannot support the posting, the error is a clear Indonesian message naming the item, not raw English', function (): void {
    $session = app(OpnameService::class)->startSession([
        'tenant_id' => $this->tenant->id,
        'outlet_id' => $this->outlet->id,
        'department_id' => $this->bar->id,
        'type' => \App\Modules\Operations\Models\OpnameSession::TYPE_DAILY,
        'opname_date' => now()->toDateString(),
    ], $this->admin->id);

    $row = $session->items->firstWhere('item_id', $this->item->id);
    app(OpnameService::class)->updateItem($row, '0', '700');
    app(OpnameService::class)->submit($session->fresh(), $this->admin->id);

    // Fisik dipaksa NEGATIF via injeksi langsung SETELAH submit --
    // satu-satunya cara realistis memaksa approve() tetap gagal walau
    // sudah pakai saldo live (physical_qty_base tidak pernah negatif
    // lewat form normal/validasi biasa).
    $row->update(['physical_qty_base' => '-50.000000']);

    try {
        app(OpnameService::class)->approve($session->fresh(), $this->admin->id);
        $this->fail('Seharusnya melempar ValidationException.');
    } catch (\Illuminate\Validation\ValidationException $exception) {
        $message = collect($exception->errors())->flatten()->implode(' ');

        expect($message)->toContain($this->item->name)
            ->and($message)->not->toContain('Stock balance is not sufficient');
    }
});
