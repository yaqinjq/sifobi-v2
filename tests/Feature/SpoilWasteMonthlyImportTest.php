<?php

use App\Jobs\ProcessSpoilWasteMonthlyImportJob;
use App\Models\MonthlyImportRun;
use App\Models\User;
use App\Modules\Core\Models\Department;
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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed([
        RolesAndPermissionsSeeder::class,
        MinimumMasterDataSeeder::class,
    ]);

    $this->admin = User::query()->where('email', 'admin@sifobi.test')->firstOrFail();
    $this->tenant = Tenant::query()->where('code', 'MKO')->firstOrFail();
    $this->outlet = Outlet::query()->where('code', 'MKO_OUTLET_1')->firstOrFail();
    $this->bar = Department::query()->where('code', 'BAR')->firstOrFail();
    $this->item = Item::query()->where('canonical_sku', 'MKO-AJINOMOTO-500GR')->firstOrFail();
    $this->item->update(['primary_department_id' => $this->bar->id]);
});

/**
 * @param  array<string, array<string, string>>  $qtyByDateBySku
 */
function buildSpoilMonthlyXlsx(array $dates, array $qtyByDateBySku, string $defaultReason, string $filename): string
{
    $path = storage_path('framework/testing/'.$filename);
    File::ensureDirectoryExists(dirname($path));

    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('SPOIL_BULANAN');

    $header = array_merge(['Kategori', 'Item', 'SKU', 'Satuan Hitung', 'Kategori Alasan (default)'], $dates);
    foreach ($header as $col => $value) {
        $sheet->getCellByColumnAndRow($col + 1, 1)->setValueExplicit($value, DataType::TYPE_STRING);
    }

    $skus = [];
    foreach ($qtyByDateBySku as $date => $bySku) {
        foreach (array_keys($bySku) as $sku) {
            $skus[$sku] = true;
        }
    }

    $row = 2;
    foreach (array_keys($skus) as $sku) {
        $sheet->setCellValue("A{$row}", 'Bumbu');
        $sheet->setCellValue("B{$row}", $sku);
        $sheet->setCellValue("C{$row}", $sku);
        $sheet->setCellValue("D{$row}", 'GR');
        $sheet->setCellValue("E{$row}", $defaultReason);

        foreach ($dates as $col => $date) {
            $qty = $qtyByDateBySku[$date][$sku] ?? '';
            $sheet->setCellValueByColumnAndRow($col + 6, $row, $qty);
        }
        $row++;
    }

    (new Xlsx($spreadsheet))->save($path);

    return $path;
}

function uploadSpoilMonthlyFile(string $path): UploadedFile
{
    return new UploadedFile($path, basename($path), 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
}

test('preview classifies cells as creatable, update_unposted, update_posted, or unchanged', function (): void {
    app(StockLedgerService::class)->openStock([
        'tenant_id' => $this->tenant->id,
        'outlet_id' => $this->outlet->id,
        'item_id' => $this->item->id,
        'unit_id' => $this->item->base_unit_id,
        'stock_target' => StockMutation::TARGET_OUTLET_DAILY,
        'qty' => '1000',
        'performed_by' => $this->admin->id,
    ]);

    // 11-02: sudah ada laporan PENDING (belum posting) dengan qty beda.
    app(SpoilWasteService::class)->record([
        'tenant_id' => $this->tenant->id,
        'outlet_id' => $this->outlet->id,
        'department_id' => $this->bar->id,
        'item_id' => $this->item->id,
        'qty_whole' => '0',
        'qty_loose' => '3',
        'recorded_date' => '2026-11-02',
        'reason_category' => SpoilWaste::REASON_RUSAK,
    ], $this->admin->id);

    // 11-03: sudah ada laporan APPROVED (sudah posting) dengan qty beda.
    $posted = app(SpoilWasteService::class)->record([
        'tenant_id' => $this->tenant->id,
        'outlet_id' => $this->outlet->id,
        'department_id' => $this->bar->id,
        'item_id' => $this->item->id,
        'qty_whole' => '0',
        'qty_loose' => '4',
        'recorded_date' => '2026-11-03',
        'reason_category' => SpoilWaste::REASON_RUSAK,
    ], $this->admin->id);
    app(SpoilWasteService::class)->approve($posted, $this->admin->id);

    // 11-04: sudah ada laporan APPROVED dengan qty & alasan SAMA persis.
    $unchanged = app(SpoilWasteService::class)->record([
        'tenant_id' => $this->tenant->id,
        'outlet_id' => $this->outlet->id,
        'department_id' => $this->bar->id,
        'item_id' => $this->item->id,
        'qty_whole' => '0',
        'qty_loose' => '5',
        'recorded_date' => '2026-11-04',
        'reason_category' => SpoilWaste::REASON_RUSAK,
    ], $this->admin->id);
    app(SpoilWasteService::class)->approve($unchanged, $this->admin->id);

    $path = buildSpoilMonthlyXlsx(
        ['2026-11-01', '2026-11-02', '2026-11-03', '2026-11-04'],
        [
            '2026-11-01' => ['MKO-AJINOMOTO-500GR' => '2'],
            '2026-11-02' => ['MKO-AJINOMOTO-500GR' => '6'],
            '2026-11-03' => ['MKO-AJINOMOTO-500GR' => '9'],
            '2026-11-04' => ['MKO-AJINOMOTO-500GR' => '5'],
        ],
        'Rusak/Cacat',
        'spoil-monthly-preview.xlsx'
    );

    $response = $this->actingAs($this->admin)->post(route('operations.spoil-wastes.import-bulanan.preview'), [
        'outlet_id' => $this->outlet->id,
        'department_id' => $this->bar->id,
        'month' => '2026-11',
        'file' => uploadSpoilMonthlyFile($path),
    ]);

    $response->assertOk();
    $cells = collect($response->json('cells'))->keyBy('date');

    expect($cells['2026-11-01']['row_status'])->toBe('creatable')
        ->and($cells['2026-11-02']['row_status'])->toBe('update_unposted')
        ->and($cells['2026-11-03']['row_status'])->toBe('update_posted')
        ->and($cells['2026-11-04']['row_status'])->toBe('unchanged');

    @unlink($path);
});

test('job creates, updates pending, and void+recreates approved cells, processed in ascending date order', function (): void {
    app(StockLedgerService::class)->openStock([
        'tenant_id' => $this->tenant->id,
        'outlet_id' => $this->outlet->id,
        'item_id' => $this->item->id,
        'unit_id' => $this->item->base_unit_id,
        'stock_target' => StockMutation::TARGET_OUTLET_DAILY,
        'qty' => '1000',
        'performed_by' => $this->admin->id,
    ]);

    // 11-02: laporan APPROVED existing qty=4 -- akan diganti jadi qty=9
    // lewat void+recreate.
    $posted = app(SpoilWasteService::class)->record([
        'tenant_id' => $this->tenant->id,
        'outlet_id' => $this->outlet->id,
        'department_id' => $this->bar->id,
        'item_id' => $this->item->id,
        'qty_whole' => '0',
        'qty_loose' => '4',
        'recorded_date' => '2026-11-02',
        'reason_category' => SpoilWaste::REASON_RUSAK,
    ], $this->admin->id);
    app(SpoilWasteService::class)->approve($posted, $this->admin->id);
    $oldMutationId = $posted->fresh()->mutation_id;

    $path = buildSpoilMonthlyXlsx(
        ['2026-11-01', '2026-11-02'],
        [
            '2026-11-01' => ['MKO-AJINOMOTO-500GR' => '2'],
            '2026-11-02' => ['MKO-AJINOMOTO-500GR' => '9'],
        ],
        'Rusak/Cacat',
        'spoil-monthly-apply.xlsx'
    );

    $run = MonthlyImportRun::query()->create([
        'tenant_id' => $this->tenant->id,
        'type' => MonthlyImportRun::TYPE_SPOIL,
        'scope' => [
            'outlet_id' => $this->outlet->id,
            'department_id' => $this->bar->id,
            'month' => '2026-11',
            'selected_cell_keys' => [
                "2026-11-02:{$this->item->id}",
                "2026-11-01:{$this->item->id}",
            ],
        ],
        'status' => MonthlyImportRun::STATUS_QUEUED,
        'upload_path' => 'tmp-imports/test-spoil-monthly.xlsx',
        'created_by' => $this->admin->id,
    ]);

    Storage::disk('local')->put($run->upload_path, file_get_contents($path));

    (new ProcessSpoilWasteMonthlyImportJob($run->id))->handle(app(SpoilWasteService::class), app(StockLedgerService::class));

    $run->refresh();
    expect($run->status)->toBe(MonthlyImportRun::STATUS_DONE)
        ->and($run->total_units)->toBe(2)
        ->and($run->processed_units)->toBe(2)
        ->and($run->failed_units)->toBe(0);

    expect(SpoilWaste::query()->where('item_id', $this->item->id)->count())->toBe(2);

    $new = SpoilWaste::query()->where('item_id', $this->item->id)->whereDate('recorded_date', '2026-11-01')->firstOrFail();
    expect($new->status)->toBe(SpoilWaste::STATUS_APPROVED)
        ->and((string) $new->qty_in_base_unit)->toBe('2.000000');

    $updated = SpoilWaste::query()->where('item_id', $this->item->id)->whereDate('recorded_date', '2026-11-02')->firstOrFail();
    expect($updated->status)->toBe(SpoilWaste::STATUS_APPROVED)
        ->and((string) $updated->qty_in_base_unit)->toBe('9.000000')
        ->and($updated->mutation_id)->not->toBe($oldMutationId);

    $hasVoidReversal = StockMutation::query()
        ->where('source_mutation_id', $oldMutationId)
        ->where('mutation_type', StockMutation::TYPE_VOID_REVERSAL)
        ->exists();
    expect($hasVoidReversal)->toBeTrue();

    $balance = StockBalance::query()
        ->where('item_id', $this->item->id)
        ->where('outlet_id', $this->outlet->id)
        ->where('stock_target', StockMutation::TARGET_OUTLET_DAILY)
        ->firstOrFail();

    // 1000 awal - 2 (01) - 9 (02, nilai baru menggantikan 4 yang lama) = 989.
    expect((string) $balance->qty_on_hand)->toBe('989.000000');
});

test('job isolates a failing cell and still processes the rest', function (): void {
    app(StockLedgerService::class)->openStock([
        'tenant_id' => $this->tenant->id,
        'outlet_id' => $this->outlet->id,
        'item_id' => $this->item->id,
        'unit_id' => $this->item->base_unit_id,
        'stock_target' => StockMutation::TARGET_OUTLET_DAILY,
        'qty' => '5',
        'performed_by' => $this->admin->id,
    ]);

    // Tanggal 02 minta qty 100 -- jauh melebihi saldo (5), harus gagal di
    // approve() tapi tidak boleh menghentikan tanggal 01 & 03.
    $path = buildSpoilMonthlyXlsx(
        ['2026-11-01', '2026-11-02', '2026-11-03'],
        [
            '2026-11-01' => ['MKO-AJINOMOTO-500GR' => '1'],
            '2026-11-02' => ['MKO-AJINOMOTO-500GR' => '100'],
            '2026-11-03' => ['MKO-AJINOMOTO-500GR' => '1'],
        ],
        'Rusak/Cacat',
        'spoil-monthly-apply-fail.xlsx'
    );

    $run = MonthlyImportRun::query()->create([
        'tenant_id' => $this->tenant->id,
        'type' => MonthlyImportRun::TYPE_SPOIL,
        'scope' => [
            'outlet_id' => $this->outlet->id,
            'department_id' => $this->bar->id,
            'month' => '2026-11',
            'selected_cell_keys' => [
                "2026-11-01:{$this->item->id}",
                "2026-11-02:{$this->item->id}",
                "2026-11-03:{$this->item->id}",
            ],
        ],
        'status' => MonthlyImportRun::STATUS_QUEUED,
        'upload_path' => 'tmp-imports/test-spoil-monthly-fail.xlsx',
        'created_by' => $this->admin->id,
    ]);

    Storage::disk('local')->put($run->upload_path, file_get_contents($path));

    (new ProcessSpoilWasteMonthlyImportJob($run->id))->handle(app(SpoilWasteService::class), app(StockLedgerService::class));

    $run->refresh();
    expect($run->status)->toBe(MonthlyImportRun::STATUS_DONE)
        ->and($run->total_units)->toBe(3)
        ->and($run->processed_units)->toBe(2)
        ->and($run->failed_units)->toBe(1)
        ->and($run->errors)->toHaveCount(1);

    $day1 = SpoilWaste::query()->where('item_id', $this->item->id)->whereDate('recorded_date', '2026-11-01')->firstOrFail();
    $day2 = SpoilWaste::query()->where('item_id', $this->item->id)->whereDate('recorded_date', '2026-11-02')->firstOrFail();
    $day3 = SpoilWaste::query()->where('item_id', $this->item->id)->whereDate('recorded_date', '2026-11-03')->firstOrFail();

    expect($day1->status)->toBe(SpoilWaste::STATUS_APPROVED)
        ->and($day2->status)->toBe(SpoilWaste::STATUS_PENDING)
        ->and($day2->mutation_id)->toBeNull()
        ->and($day3->status)->toBe(SpoilWaste::STATUS_APPROVED);
});
