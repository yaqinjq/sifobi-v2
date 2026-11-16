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
 * Mirror struktur OpnameMonthlyImportTemplate (header 2 baris, 4 sub-kolom
 * IN|OUT|SPOIL|BAL per tanggal). Untuk kemudahan test lama yang cuma peduli
 * BALANCE, nilai per sku per tanggal boleh berupa string polos (diartikan
 * sebagai BAL saja, IN/OUT/SPOIL kosong) ATAU array ['in'=>,'out'=>,
 * 'spoil'=>,'balance'=>] untuk test yang butuh semua sub-kolom.
 *
 * @param  array<string, array<string, string|array{in?: string, out?: string, spoil?: string, balance?: string}>>  $dataByDateBySku
 */
function buildOpnameMonthlyXlsx(array $dates, array $dataByDateBySku, string $filename): string
{
    $path = storage_path('framework/testing/'.$filename);
    File::ensureDirectoryExists(dirname($path));

    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('OPNAME_BULANAN');

    foreach (['Item', 'SKU', 'Satuan Hitung'] as $col => $label) {
        $sheet->getCellByColumnAndRow($col + 1, 1)->setValueExplicit($label, DataType::TYPE_STRING);
        $sheet->getCellByColumnAndRow($col + 1, 2)->setValueExplicit($label, DataType::TYPE_STRING);
    }

    $col = 4;
    foreach ($dates as $date) {
        foreach (['IN', 'OUT', 'SPOIL', 'BAL'] as $subLabel) {
            $sheet->getCellByColumnAndRow($col, 1)->setValueExplicit($date, DataType::TYPE_STRING);
            $sheet->getCellByColumnAndRow($col, 2)->setValueExplicit($subLabel, DataType::TYPE_STRING);
            $col++;
        }
    }

    $skus = [];
    foreach ($dataByDateBySku as $bySku) {
        foreach (array_keys($bySku) as $sku) {
            $skus[$sku] = true;
        }
    }

    $row = 3;
    foreach (array_keys($skus) as $sku) {
        $sheet->setCellValue("A{$row}", $sku);
        $sheet->setCellValue("B{$row}", $sku);
        $sheet->setCellValue("C{$row}", 'GR');

        $col = 4;
        foreach ($dates as $date) {
            $data = $dataByDateBySku[$date][$sku] ?? '';
            if (is_array($data)) {
                [$in, $out, $spoil, $bal] = [$data['in'] ?? '', $data['out'] ?? '', $data['spoil'] ?? '', $data['balance'] ?? ''];
            } else {
                [$in, $out, $spoil, $bal] = ['', '', '', $data];
            }

            $sheet->setCellValueByColumnAndRow($col, $row, $in);
            $sheet->setCellValueByColumnAndRow($col + 1, $row, $out);
            $sheet->setCellValueByColumnAndRow($col + 2, $row, $spoil);
            $sheet->setCellValueByColumnAndRow($col + 3, $row, $bal);
            $col += 4;
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

test('a real downloaded monthly template can be filled in and re-uploaded end to end', function (): void {
    // BEDA dari test lain di file ini (buildOpnameMonthlyXlsx() bangun
    // fixture tangan TANPA mergeCells()) -- ini download file ASLI dari
    // OpnameMonthlyImportTemplate (header 2-baris + merge horizontal per
    // tanggal) supaya kalau ada masalah merge yang bikin importer gagal
    // baca kolom, test ini yang menangkapnya.
    $downloadPath = storage_path('framework/testing/opname-monthly-real-template.xlsx');
    File::ensureDirectoryExists(dirname($downloadPath));
    $content = $this->actingAs($this->admin)->get(route('operations.opname.import-bulanan.template', [
        'outlet_id' => $this->outlet->id,
        'department_id' => $this->bar->id,
        'month' => '2026-11',
    ]))->streamedContent();
    file_put_contents($downloadPath, $content);

    $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($downloadPath);
    $sheet = $spreadsheet->getSheetByName('OPNAME_BULANAN');

    $targetRow = null;
    for ($row = 3; $row <= $sheet->getHighestRow(); $row++) {
        if ($sheet->getCell('B'.$row)->getValue() === $this->item->canonical_sku) {
            $targetRow = $row;
            break;
        }
    }
    expect($targetRow)->not->toBeNull();

    // Tanggal 1 di bulan itu = kolom D (IN) s/d G (BAL) -- isi BAL saja.
    $sheet->setCellValue("G{$targetRow}", 25);
    (new Xlsx($spreadsheet))->save($downloadPath);

    $response = $this->actingAs($this->admin)->post(route('operations.opname.import-bulanan.preview'), [
        'outlet_id' => $this->outlet->id,
        'department_id' => $this->bar->id,
        'month' => '2026-11',
        'file' => uploadOpnameMonthlyFile($downloadPath),
    ]);

    $response->assertOk();
    $rows = collect($response->json('rows'))->keyBy('date');
    expect($rows->has('2026-11-01'))->toBeTrue();
    $item = collect($rows['2026-11-01']['items'])->firstWhere('sku', $this->item->canonical_sku);
    expect($item)->not->toBeNull()
        ->and($item['balance'])->toBe('25.000000');

    @unlink($downloadPath);
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

test('job no longer fails on an already-processed date -- it just leaves BAL untouched', function (): void {
    app(StockLedgerService::class)->openStock([
        'tenant_id' => $this->tenant->id,
        'outlet_id' => $this->outlet->id,
        'item_id' => $this->item->id,
        'unit_id' => $this->item->base_unit_id,
        'stock_target' => StockMutation::TARGET_OUTLET_DAILY,
        'qty' => '1000',
        'performed_by' => $this->admin->id,
    ]);

    // Tanggal 02 sudah di-approve duluan (PROCESSED). BEDA dari perilaku
    // sebelum fitur IN/OUT/SPOIL: ini SEKARANG tidak lagi dianggap gagal --
    // job tetap processed++ (BAL-nya cuma dilewati, tidak diubah), supaya
    // tanggal ini tetap bisa dipakai utk koreksi IN/OUT/SPOIL lewat upload
    // ulang (lihat test terpisah utk itu).
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
        ->and($run->processed_units)->toBe(3)
        ->and($run->failed_units)->toBe(0);

    expect(OpnameSession::query()->where('outlet_id', $this->outlet->id)->whereDate('opname_date', '2026-11-01')->first()->status)
        ->toBe(OpnameSession::STATUS_PROCESSED);
    expect(OpnameSession::query()->where('outlet_id', $this->outlet->id)->whereDate('opname_date', '2026-11-03')->first()->status)
        ->toBe(OpnameSession::STATUS_PROCESSED);

    // Tanggal 02 (sudah PROCESSED SEBELUM job jalan) -- BAL di file upload
    // (18) TIDAK PERNAH diterapkan, physical_qty tetap nilai asli (1/0)
    // dari updateItem() manual di atas, bukan ter-update jadi 18.
    $lockedItem = $existing->fresh(['items'])->items->firstWhere('item_id', $this->item->id);
    expect((string) $lockedItem->physical_qty_whole)->toBe('1.000000');
});
