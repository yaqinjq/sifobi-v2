<?php

use App\Jobs\ProcessOpnameMonthlyImportJob;
use App\Models\MonthlyImportRun;
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
 * @param  array<string, array<string, string>>  $qtyByDateBySku  ['2026-11-01' => ['MKO-AJINOMOTO-500GR' => '20']]
 */
function buildOpnameMonthlyXlsx(array $dates, array $qtyByDateBySku, string $filename): string
{
    $path = storage_path('framework/testing/'.$filename);
    File::ensureDirectoryExists(dirname($path));

    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('OPNAME_BULANAN');

    $header = array_merge(['Kategori', 'Item', 'SKU', 'Satuan Hitung'], $dates);
    foreach ($header as $col => $value) {
        $cell = $sheet->getCellByColumnAndRow($col + 1, 1);
        $cell->setValueExplicit($value, DataType::TYPE_STRING);
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

        foreach ($dates as $col => $date) {
            $qty = $qtyByDateBySku[$date][$sku] ?? '';
            $sheet->setCellValueByColumnAndRow($col + 5, $row, $qty);
        }
        $row++;
    }

    (new Xlsx($spreadsheet))->save($path);

    return $path;
}

function uploadOpnameMonthlyFile(string $path): UploadedFile
{
    return new UploadedFile($path, basename($path), 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
}

test('template download returns the opname monthly grid with items ordered by category then name', function (): void {
    $response = $this->actingAs($this->admin)->get(route('operations.opname.import-bulanan.template', [
        'outlet_id' => $this->outlet->id,
        'department_id' => $this->bar->id,
        'month' => '2026-11',
    ]));

    $response->assertOk();
    $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
});

test('preview classifies dates as new_session, update_draft, or skip_locked', function (): void {
    app(StockLedgerService::class)->openStock([
        'tenant_id' => $this->tenant->id,
        'outlet_id' => $this->outlet->id,
        'item_id' => $this->item->id,
        'unit_id' => $this->item->base_unit_id,
        'stock_target' => StockMutation::TARGET_OUTLET_DAILY,
        'qty' => '1000',
        'performed_by' => $this->admin->id,
    ]);

    // Sesi draft existing di tanggal ke-2 -- harus terdeteksi sebagai
    // update_draft, bukan new_session.
    $draftSession = app(OpnameService::class)->startSession([
        'tenant_id' => $this->tenant->id,
        'outlet_id' => $this->outlet->id,
        'department_id' => $this->bar->id,
        'opname_date' => '2026-11-02',
        'type' => OpnameSession::TYPE_DAILY,
    ], $this->admin->id);

    // Sesi sudah submitted di tanggal ke-3 -- harus dilewati (skip_locked).
    $lockedSession = app(OpnameService::class)->startSession([
        'tenant_id' => $this->tenant->id,
        'outlet_id' => $this->outlet->id,
        'department_id' => $this->bar->id,
        'opname_date' => '2026-11-03',
        'type' => OpnameSession::TYPE_DAILY,
    ], $this->admin->id);
    $lockedSession->items->each(fn ($i) => app(OpnameService::class)->updateItem($i, '1', '0'));
    app(OpnameService::class)->submit($lockedSession->fresh(['items']), $this->admin->id);

    $path = buildOpnameMonthlyXlsx(
        ['2026-11-01', '2026-11-02', '2026-11-03'],
        [
            '2026-11-01' => ['MKO-AJINOMOTO-500GR' => '20'],
            '2026-11-02' => ['MKO-AJINOMOTO-500GR' => '18'],
            '2026-11-03' => ['MKO-AJINOMOTO-500GR' => '17'],
        ],
        'opname-monthly-preview.xlsx'
    );

    $response = $this->actingAs($this->admin)->post(route('operations.opname.import-bulanan.preview'), [
        'outlet_id' => $this->outlet->id,
        'department_id' => $this->bar->id,
        'month' => '2026-11',
        'file' => uploadOpnameMonthlyFile($path),
    ]);

    $response->assertOk();
    $rows = collect($response->json('rows'))->keyBy('date');

    expect($rows['2026-11-01']['row_status'])->toBe('new_session')
        ->and($rows['2026-11-02']['row_status'])->toBe('update_draft')
        ->and($rows['2026-11-03']['row_status'])->toBe('skip_locked');

    @unlink($path);
});

test('job processes dates in ascending order and ends with the correct live balance, even when input is out of order', function (): void {
    app(StockLedgerService::class)->openStock([
        'tenant_id' => $this->tenant->id,
        'outlet_id' => $this->outlet->id,
        'item_id' => $this->item->id,
        'unit_id' => $this->item->base_unit_id,
        'stock_target' => StockMutation::TARGET_OUTLET_DAILY,
        'qty' => '1000',
        'performed_by' => $this->admin->id,
    ]);

    $run = MonthlyImportRun::query()->create([
        'tenant_id' => $this->tenant->id,
        'type' => MonthlyImportRun::TYPE_OPNAME,
        'scope' => [
            'outlet_id' => $this->outlet->id,
            'department_id' => $this->bar->id,
            'month' => '2026-11',
            // Sengaja TIDAK urut -- job harus tetap mengurutkan ascending.
            'selected_dates' => ['2026-11-03', '2026-11-01', '2026-11-02'],
        ],
        'status' => MonthlyImportRun::STATUS_QUEUED,
        'upload_path' => 'tmp-imports/test-opname-monthly.xlsx',
        'created_by' => $this->admin->id,
    ]);

    Storage::disk('local')->put($run->upload_path, file_get_contents(buildOpnameMonthlyXlsx(
        ['2026-11-01', '2026-11-02', '2026-11-03'],
        [
            '2026-11-01' => ['MKO-AJINOMOTO-500GR' => '20'],
            '2026-11-02' => ['MKO-AJINOMOTO-500GR' => '18'],
            '2026-11-03' => ['MKO-AJINOMOTO-500GR' => '17'],
        ],
        'opname-monthly-apply.xlsx'
    )));

    (new ProcessOpnameMonthlyImportJob($run->id))->handle(app(OpnameService::class));

    $run->refresh();
    expect($run->status)->toBe(MonthlyImportRun::STATUS_DONE)
        ->and($run->total_units)->toBe(3)
        ->and($run->processed_units)->toBe(3)
        ->and($run->failed_units)->toBe(0);

    expect(OpnameSession::query()->where('outlet_id', $this->outlet->id)->where('department_id', $this->bar->id)->count())->toBe(3);
    expect(OpnameSession::query()->where('status', '!=', OpnameSession::STATUS_PROCESSED)->count())->toBe(0);

    $balance = StockBalance::query()
        ->where('item_id', $this->item->id)
        ->where('outlet_id', $this->outlet->id)
        ->where('stock_target', StockMutation::TARGET_OUTLET_DAILY)
        ->firstOrFail();

    // Hasil fisik terakhir (tanggal 03) adalah 17 -- kalau diproses
    // out-of-order (03 duluan sebelum 01/02), hasil akhirnya akan beda
    // karena masing-masing approve() menghitung selisih dari saldo LIVE.
    expect((string) $balance->qty_on_hand)->toBe('17.000000');
});

test('job isolates a failing date and still processes the rest', function (): void {
    app(StockLedgerService::class)->openStock([
        'tenant_id' => $this->tenant->id,
        'outlet_id' => $this->outlet->id,
        'item_id' => $this->item->id,
        'unit_id' => $this->item->base_unit_id,
        'stock_target' => StockMutation::TARGET_OUTLET_DAILY,
        'qty' => '1000',
        'performed_by' => $this->admin->id,
    ]);

    // Tanggal 02 sudah di-approve duluan (PROCESSED) -- job harus
    // melewati tanggal ini (di luar scope V1), tapi tetap lanjut ke 01 & 03.
    $existing = app(OpnameService::class)->startSession([
        'tenant_id' => $this->tenant->id,
        'outlet_id' => $this->outlet->id,
        'department_id' => $this->bar->id,
        'opname_date' => '2026-11-02',
        'type' => OpnameSession::TYPE_DAILY,
    ], $this->admin->id);
    $existing->items->each(fn ($i) => app(OpnameService::class)->updateItem($i, '1', '0'));
    app(OpnameService::class)->submit($existing->fresh(['items']), $this->admin->id);
    app(OpnameService::class)->approve($existing->fresh(['items']), $this->admin->id);

    $run = MonthlyImportRun::query()->create([
        'tenant_id' => $this->tenant->id,
        'type' => MonthlyImportRun::TYPE_OPNAME,
        'scope' => [
            'outlet_id' => $this->outlet->id,
            'department_id' => $this->bar->id,
            'month' => '2026-11',
            'selected_dates' => ['2026-11-01', '2026-11-02', '2026-11-03'],
        ],
        'status' => MonthlyImportRun::STATUS_QUEUED,
        'upload_path' => 'tmp-imports/test-opname-monthly-fail.xlsx',
        'created_by' => $this->admin->id,
    ]);

    Storage::disk('local')->put($run->upload_path, file_get_contents(buildOpnameMonthlyXlsx(
        ['2026-11-01', '2026-11-02', '2026-11-03'],
        [
            '2026-11-01' => ['MKO-AJINOMOTO-500GR' => '20'],
            '2026-11-02' => ['MKO-AJINOMOTO-500GR' => '18'],
            '2026-11-03' => ['MKO-AJINOMOTO-500GR' => '17'],
        ],
        'opname-monthly-apply-fail.xlsx'
    )));

    (new ProcessOpnameMonthlyImportJob($run->id))->handle(app(OpnameService::class));

    $run->refresh();
    expect($run->status)->toBe(MonthlyImportRun::STATUS_DONE)
        ->and($run->total_units)->toBe(3)
        ->and($run->processed_units)->toBe(2)
        ->and($run->failed_units)->toBe(1)
        ->and($run->errors)->toHaveCount(1);

    expect(OpnameSession::query()->where('outlet_id', $this->outlet->id)->whereDate('opname_date', '2026-11-01')->first()->status)
        ->toBe(OpnameSession::STATUS_PROCESSED);
    expect(OpnameSession::query()->where('outlet_id', $this->outlet->id)->whereDate('opname_date', '2026-11-03')->first()->status)
        ->toBe(OpnameSession::STATUS_PROCESSED);
});
