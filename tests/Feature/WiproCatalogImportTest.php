<?php

use App\Imports\WiproCatalogImport;
use App\Modules\Core\Models\Tenant;
use App\Modules\Inventory\Models\Item;
use Database\Seeders\MinimumMasterDataSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed([
        RolesAndPermissionsSeeder::class,
        MinimumMasterDataSeeder::class,
    ]);

    $this->tenant = Tenant::query()->where('code', 'MKO')->firstOrFail();
});

/**
 * Reproduksi PERSIS layout file "STANDARISASI PRODUK WIP.xlsx" asli dari
 * pimpinan: kolom Product_Code disisipkan di depan SKU, dan kolom outlet
 * disisipkan sebelum Konversi -- menggeser semua posisi kolom dibanding
 * format lama (A=sku, B=product_name, ..., H=konversi) yang tadinya
 * hardcoded di WiproCatalogImport, bikin data ter-import SALAH TOTAL tanpa
 * error yang kelihatan.
 */
function buildNewWipLayoutFixture(string $path): void
{
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('BUMBU');
    $sheet->fromArray([
        ['Product_Code', 'sku', 'product_name', 'product_type', 'default_unit_symbol', 'is_active', 'kategori', 'CK', 'outlet', 'konversi'],
        ['KWIP0546', 'BMB-GADO', 'BUMBU GADO-GADO', 'wip', 'PACK', 1, 'BUMBU', 'MULTIRASA KOPI JAYA - KK', 'MKO, AHPEK', '1 PACK = 1 KG'],
        ['KWIP0547', 'BMB-GULAI', 'BUMBU GULAI', 'wip', 'PACK', 0, 'BUMBU', 'MULTIRASA KOPI JAYA - KK', 'MKO, AHPEK', '2 PACK = 3 KG'],
    ]);
    (new Xlsx($spreadsheet))->save($path);
}

test('import correctly maps columns for the new leadership WIP file layout', function (): void {
    $path = storage_path('framework/testing/wip-new-layout.xlsx');
    File::ensureDirectoryExists(dirname($path));
    buildNewWipLayoutFixture($path);

    $import = new WiproCatalogImport($this->tenant->id);
    $import->import($path);
    $summary = $import->summary();

    expect($summary['errors'])->toBe([])
        ->and($summary['inserted'])->toBe(2);

    $item = Item::query()->where('tenant_id', $this->tenant->id)->where('canonical_sku', 'BMB-GADO')->firstOrFail();

    expect($item->name)->toBe('BUMBU GADO-GADO')
        ->and($item->is_active)->toBeTrue()
        ->and($item->category?->name)->toBe('Wipro — Bumbu')
        ->and($item->purchaseUnit?->code)->toBe('PACK')
        ->and($item->baseUnit?->code)->toBe('KG')
        ->and((string) $item->purchase_ratio)->toBe('1.000000');

    $inactiveItem = Item::query()->where('tenant_id', $this->tenant->id)->where('canonical_sku', 'BMB-GULAI')->firstOrFail();

    expect($inactiveItem->is_active)->toBeFalse()
        ->and((string) $inactiveItem->purchase_ratio)->toBe('1.500000');
});

test('import still supports the legacy 8 column layout without a Product_Code or outlet column', function (): void {
    $path = storage_path('framework/testing/wip-legacy-layout.xlsx');
    File::ensureDirectoryExists(dirname($path));

    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('BUMBU');
    $sheet->fromArray([
        ['sku', 'product_name', 'product_type', 'default_unit_symbol', 'is_active', 'kategori', 'CK', 'konversi'],
        ['BMB-GADO', 'BUMBU GADO-GADO', 'wip', 'PACK', 1, 'BUMBU', 'MULTIRASA KOPI JAYA - KK', '1 PACK = 1 KG'],
    ]);
    (new Xlsx($spreadsheet))->save($path);

    $import = new WiproCatalogImport($this->tenant->id);
    $import->import($path);
    $summary = $import->summary();

    expect($summary['errors'])->toBe([])
        ->and($summary['inserted'])->toBe(1);

    $item = Item::query()->where('tenant_id', $this->tenant->id)->where('canonical_sku', 'BMB-GADO')->firstOrFail();
    expect($item->name)->toBe('BUMBU GADO-GADO')
        ->and($item->is_active)->toBeTrue();
});

test('import reports a clear error for a sheet with no recognizable header columns', function (): void {
    $path = storage_path('framework/testing/wip-bad-layout.xlsx');
    File::ensureDirectoryExists(dirname($path));

    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('RANDOM');
    $sheet->fromArray([
        ['foo', 'bar', 'baz'],
        ['1', '2', '3'],
    ]);
    (new Xlsx($spreadsheet))->save($path);

    $import = new WiproCatalogImport($this->tenant->id);
    $import->import($path);
    $summary = $import->summary();

    expect($summary['inserted'])->toBe(0)
        ->and($summary['errors'])->toHaveCount(1)
        ->and($summary['errors'][0])->toContain('format kolom tidak dikenali');
});
