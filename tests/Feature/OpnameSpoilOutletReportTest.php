<?php

use App\Models\User;
use App\Modules\Core\Models\Department;
use App\Modules\Core\Models\Outlet;
use App\Modules\Core\Models\Tenant;
use App\Modules\Inventory\Models\Item;
use App\Modules\Operations\Models\SpoilWaste;
use App\Modules\Stock\Models\StockMutation;
use App\Services\OpnameService;
use App\Services\SpoilWasteService;
use App\Services\StockLedgerService;
use Database\Seeders\MinimumMasterDataSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;

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
});

test('opname per outlet report exports a workbook with a sheet per department using real counted values', function (): void {
    $session = app(OpnameService::class)->startSession([
        'tenant_id' => $this->tenant->id,
        'outlet_id' => $this->outlet->id,
        'department_id' => $this->bar->id,
        'opname_date' => '2026-11-10',
        'type' => \App\Modules\Operations\Models\OpnameSession::TYPE_DAILY,
    ], $this->admin->id);

    $session->fresh(['items'])->items->each(fn ($i) => app(OpnameService::class)->updateItem($i, '7', '2'));
    app(OpnameService::class)->submit($session->fresh(['items']), $this->admin->id);
    app(OpnameService::class)->approve($session->fresh(['items']), $this->admin->id);

    $response = $this->actingAs($this->admin)->get(route('laporan.opname-outlet.export', [
        'outlet_id' => $this->outlet->id,
        'date' => '2026-11-10',
    ]));

    $response->assertOk();
    $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

    $path = storage_path('framework/testing/opname-outlet-report-downloaded.xlsx');
    file_put_contents($path, $response->streamedContent());

    $spreadsheet = IOFactory::load($path);
    expect($spreadsheet->getSheetNames())->toContain('RINGKASAN');
    expect($spreadsheet->getSheetNames())->toContain($this->bar->name);

    $sheet = $spreadsheet->getSheetByName($this->bar->name);
    $found = false;
    for ($row = 3; $row <= $sheet->getHighestRow(); $row++) {
        if ($sheet->getCell("B{$row}")->getValue() === $this->item->canonical_sku) {
            $found = true;
            expect((float) $sheet->getCell("I{$row}")->getValue())->toBe(7.0);
            expect((float) $sheet->getCell("J{$row}")->getValue())->toBe(2.0);

            // Total (kolom K) & TOTAL Per Satuan (kolom L) adalah rumus Excel
            // asli -- hitung ulang pakai mesin kalkulasi PhpSpreadsheet sendiri
            // (tanpa Excel) untuk memastikan sintaksnya benar DAN hasilnya
            // sesuai fisik_utuh*rasio + fisik_ecer, bukan cuma nilai mentah.
            $expectedTotal = 7.0 * (float) $this->item->inventory_ratio + 2.0;
            expect((float) $sheet->getCell("K{$row}")->getCalculatedValue())->toBe($expectedTotal);
            expect((float) $sheet->getCell("L{$row}")->getCalculatedValue())->toBe($expectedTotal);
            break;
        }
    }
    expect($found)->toBeTrue();

    @unlink($path);
});

test('spoil per outlet report exports a workbook with a sheet per department using real approved records', function (): void {
    app(StockLedgerService::class)->openStock([
        'tenant_id' => $this->tenant->id,
        'outlet_id' => $this->outlet->id,
        'item_id' => $this->item->id,
        'unit_id' => $this->item->base_unit_id,
        'stock_target' => StockMutation::TARGET_OUTLET_DAILY,
        'qty' => '100',
        'performed_by' => $this->admin->id,
    ]);

    app(SpoilWasteService::class)->record([
        'tenant_id' => $this->tenant->id,
        'outlet_id' => $this->outlet->id,
        'department_id' => $this->bar->id,
        'item_id' => $this->item->id,
        'unit_id' => $this->item->base_unit_id,
        'qty' => '3',
        'reason_category' => SpoilWaste::REASON_RUSAK,
        'recorded_date' => '2026-11-10',
        'recorded_at' => '2026-11-10 10:00:00',
    ], $this->admin->id);

    $spoil = SpoilWaste::query()->where('tenant_id', $this->tenant->id)->where('item_id', $this->item->id)->firstOrFail();
    app(SpoilWasteService::class)->approve($spoil, $this->admin->id);

    $response = $this->actingAs($this->admin)->get(route('laporan.spoil-outlet.export', [
        'outlet_id' => $this->outlet->id,
        'date_from' => '2026-11-01',
        'date_to' => '2026-11-30',
    ]));

    $response->assertOk();
    $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

    $path = storage_path('framework/testing/spoil-outlet-report-downloaded.xlsx');
    file_put_contents($path, $response->streamedContent());

    $spreadsheet = IOFactory::load($path);
    expect($spreadsheet->getSheetNames())->toContain('RINGKASAN');
    expect($spreadsheet->getSheetNames())->toContain($this->bar->name);

    $sheet = $spreadsheet->getSheetByName($this->bar->name);
    $found = false;
    for ($row = 2; $row <= $sheet->getHighestRow(); $row++) {
        if ($sheet->getCell("B{$row}")->getValue() === $this->item->canonical_sku) {
            $found = true;
            expect((float) $sheet->getCell("E{$row}")->getValue())->toBe(3.0);

            // TOTAL HARGA (kolom G) rumus Excel asli = STOK * PRICE/UNIT.
            $price = (float) ($this->item->standard_cost ?: $this->item->last_purchase_price ?: 0);
            expect((float) $sheet->getCell("G{$row}")->getCalculatedValue())->toBe(round(3.0 * $price, 2));
            break;
        }
    }
    expect($found)->toBeTrue();

    @unlink($path);
});

test('a user without access to the outlet cannot export its reports', function (): void {
    $otherOutlet = Outlet::query()->where('tenant_id', $this->tenant->id)->where('code', 'MKO_OUTLET_2')->firstOrFail();
    $user = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'outlet_id' => $otherOutlet->id,
        'department_id' => $this->bar->id,
        'status' => 'ACTIVE',
    ]);
    $user->assignRole('PIC_OUTLET');
    $user->outlets()->sync([$otherOutlet->id]);

    $this->actingAs($user)->get(route('laporan.opname-outlet.export', [
        'outlet_id' => $this->outlet->id,
        'date' => '2026-11-10',
    ]))->assertForbidden();

    $this->actingAs($user)->get(route('laporan.spoil-outlet.export', [
        'outlet_id' => $this->outlet->id,
        'date_from' => '2026-11-01',
        'date_to' => '2026-11-30',
    ]))->assertForbidden();
});

test('the filter pages for both outlet reports render without error', function (): void {
    $this->actingAs($this->admin)->get(route('laporan.opname-outlet'))->assertOk();
    $this->actingAs($this->admin)->get(route('laporan.spoil-outlet'))->assertOk();
});
