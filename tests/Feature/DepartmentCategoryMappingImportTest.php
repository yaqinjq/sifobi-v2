<?php

use App\Imports\DepartmentCategoryMappingImport;
use App\Modules\Core\Models\Department;
use App\Modules\Core\Models\Tenant;
use App\Modules\Inventory\Models\ItemCategory;
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
    $this->bar = Department::query()->where('code', 'BAR')->firstOrFail();
    $this->kitchen = Department::query()->where('code', 'KITCHEN')->firstOrFail();
});

/**
 * Reproduksi PERSIS bentuk file "Mapping Daily Opname.xlsx" asli dari
 * pimpinan: 2 baris judul kosong, header di baris ke-3 (bukan baris
 * pertama), kolom Departemen/Kategori cuma diisi sekali per kelompok
 * (baris berikutnya kosong -- gaya sel gabungan), dan ada baris kosong
 * pemisah antar kelompok departemen.
 */
function buildRealMappingFixture(string $path): void
{
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->fromArray([
        [null, null, null, null, null, null],
        [null, null, null, null, null, null],
        [null, 'DEPARTEMEN', 'GUDANG', 'KATEGORI', null, 'SUB KATEGORI'],
        ['DAILY OPNAME', 'BAR', 'GUDANG UTAMA', 'BAR WIP', 1, 'BASED (WIP)'],
        [null, null, null, null, null, null],
        ['DAILY OPNAME', 'BAR', null, 'BAR DRY GOODS', 1, 'COFFEE & TEA'],
        [null, null, null, null, 2, 'MILK'],
        [null, null, null, null, 3, 'FRUIT & VEGETABLES'],
        [null, null, null, null, 8, 'OTHER'],
        [null, null, null, null, null, null],
        [null, 'KITCHEN', 'GUDANG UTAMA', 'KITCHEN WIP', 1, 'APPETIZER'],
        [null, null, null, null, 2, 'SAUCE (PRODUCTION)'],
        [null, null, null, null, 9, 'OTHERS'],
    ]);
    (new Xlsx($spreadsheet))->save($path);
}

test('import handles the real leadership file layout with blank inherited cells', function (): void {
    $path = storage_path('framework/testing/mapping-real-layout.xlsx');
    File::ensureDirectoryExists(dirname($path));
    buildRealMappingFixture($path);

    $import = new DepartmentCategoryMappingImport($this->tenant->id);
    $import->import($path);
    $summary = $import->summary();

    expect($summary['failed'])->toBe(0)
        ->and($summary['processed'])->toBe(8);

    $barWip = ItemCategory::query()->where('tenant_id', $this->tenant->id)->where('name', 'BAR WIP')->first();
    $barDryGoods = ItemCategory::query()->where('tenant_id', $this->tenant->id)->where('name', 'BAR DRY GOODS')->first();
    $kitchenWip = ItemCategory::query()->where('tenant_id', $this->tenant->id)->where('name', 'KITCHEN WIP')->first();
    $coffeeTea = ItemCategory::query()->where('tenant_id', $this->tenant->id)->where('name', 'COFFEE & TEA')->first();
    $milk = ItemCategory::query()->where('tenant_id', $this->tenant->id)->where('name', 'MILK')->first();

    expect($barWip)->not->toBeNull()
        ->and($barDryGoods)->not->toBeNull()
        ->and($kitchenWip)->not->toBeNull()
        ->and($coffeeTea->parent_id)->toBe($barDryGoods->id)
        ->and($coffeeTea->sort_order)->toBe(1)
        ->and($milk->parent_id)->toBe($barDryGoods->id)
        ->and($milk->sort_order)->toBe(2)
        ->and($this->bar->itemCategories()->where('item_categories.id', $barDryGoods->id)->exists())->toBeTrue()
        ->and($this->kitchen->itemCategories()->where('item_categories.id', $kitchenWip->id)->exists())->toBeTrue();
});

test('import reuses an existing category by name instead of failing on duplicate', function (): void {
    // Simulasikan kategori "COFFEE & TEA" yang sudah ada lebih dulu dari
    // Master Data lama (sebelum fitur mapping ini ada) -- inilah skenario
    // asli yang bikin upload file pimpinan gagal "Duplicate entry".
    $preExisting = ItemCategory::query()->create([
        'tenant_id' => $this->tenant->id,
        'code' => 'coffee_tea_lama',
        'name' => 'COFFEE & TEA',
        'status' => 'ACTIVE',
        'is_active' => true,
    ]);

    $path = storage_path('framework/testing/mapping-real-layout-2.xlsx');
    File::ensureDirectoryExists(dirname($path));
    buildRealMappingFixture($path);

    $import = new DepartmentCategoryMappingImport($this->tenant->id);
    $import->import($path);
    $summary = $import->summary();

    expect($summary['failed'])->toBe(0);

    $barDryGoods = ItemCategory::query()->where('tenant_id', $this->tenant->id)->where('name', 'BAR DRY GOODS')->first();

    expect(ItemCategory::query()->where('tenant_id', $this->tenant->id)->where('name', 'COFFEE & TEA')->count())->toBe(1)
        ->and($preExisting->refresh()->parent_id)->toBe($barDryGoods->id);
});

test('import still supports the simple flat template with department repeated every row', function (): void {
    $path = storage_path('framework/testing/mapping-flat-layout.xlsx');
    File::ensureDirectoryExists(dirname($path));

    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->fromArray([
        ['departemen', 'kategori', 'urutan', 'sub_kategori'],
        ['BAR', 'BAR DRY GOODS', '1', 'COFFEE & TEA'],
        ['BAR', 'BAR DRY GOODS', '2', 'MILK'],
    ]);
    (new Xlsx($spreadsheet))->save($path);

    $import = new DepartmentCategoryMappingImport($this->tenant->id);
    $import->import($path);
    $summary = $import->summary();

    expect($summary['failed'])->toBe(0)
        ->and($summary['processed'])->toBe(2);

    expect(ItemCategory::query()->where('tenant_id', $this->tenant->id)->where('name', 'MILK')->exists())->toBeTrue();
});

test('import reports an error for an unknown department without crashing the whole file', function (): void {
    $path = storage_path('framework/testing/mapping-unknown-dept.xlsx');
    File::ensureDirectoryExists(dirname($path));

    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->fromArray([
        ['departemen', 'kategori', 'urutan', 'sub_kategori'],
        ['DEPARTEMEN_TIDAK_ADA', 'BAR DRY GOODS', '1', 'COFFEE & TEA'],
        ['BAR', 'BAR DRY GOODS', '2', 'MILK'],
    ]);
    (new Xlsx($spreadsheet))->save($path);

    $import = new DepartmentCategoryMappingImport($this->tenant->id);
    $import->import($path);
    $summary = $import->summary();

    expect($summary['processed'])->toBe(1)
        ->and($summary['failed'])->toBe(1)
        ->and($summary['errors'][0]['message'])->toContain('tidak ditemukan');
});
