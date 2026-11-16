<?php

use App\Jobs\ProcessOpnameMonthlyImportJob;
use App\Models\MonthlyImportRun;
use App\Models\User;
use App\Modules\Core\Models\Department;
use App\Modules\Core\Models\Outlet;
use App\Modules\Core\Models\Tenant;
use App\Modules\Inventory\Models\Item;
use App\Modules\Operations\Models\OpnameItem;
use App\Modules\Operations\Models\OpnameSession;
use App\Modules\Stock\Models\StockBalance;
use App\Modules\Stock\Models\StockMutation;
use App\Services\OpnameService;
use App\Services\StockLedgerService;
use Database\Seeders\MinimumMasterDataSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

/**
 * Mirror struktur OpnameMonthlyImportTemplate (header 2 baris, 4 sub-kolom
 * IN|OUT|SPOIL|BAL per tanggal). Nama sengaja beda dari helper serupa di
 * OpnameMonthlyImportTest.php supaya tidak bentrok "cannot redeclare
 * function" kalau kedua file ini ikut ter-load bareng (full suite run).
 *
 * @param  array<string, array<string, array{in?: string, out?: string, spoil?: string, balance?: string}>>  $dataByDateBySku
 */
function buildOpnameMonthlyReportXlsx(array $dates, array $dataByDateBySku, string $filename): string
{
    $path = storage_path('framework/testing/'.$filename);
    \Illuminate\Support\Facades\File::ensureDirectoryExists(dirname($path));

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
            $data = $dataByDateBySku[$date][$sku] ?? [];
            $sheet->setCellValueByColumnAndRow($col, $row, $data['in'] ?? '');
            $sheet->setCellValueByColumnAndRow($col + 1, $row, $data['out'] ?? '');
            $sheet->setCellValueByColumnAndRow($col + 2, $row, $data['spoil'] ?? '');
            $sheet->setCellValueByColumnAndRow($col + 3, $row, $data['balance'] ?? '');
            $col += 4;
        }
        $row++;
    }

    (new Xlsx($spreadsheet))->save($path);

    return $path;
}

function runMonthlyImport(object $test, array $dates, array $dataByDateBySku, string $filename): MonthlyImportRun
{
    $run = MonthlyImportRun::query()->create([
        'tenant_id' => $test->tenant->id,
        'type' => MonthlyImportRun::TYPE_OPNAME,
        'scope' => [
            'outlet_id' => $test->outlet->id,
            'department_id' => $test->bar->id,
            'month' => substr($dates[0], 0, 7),
            'selected_dates' => $dates,
        ],
        'status' => MonthlyImportRun::STATUS_QUEUED,
        'upload_path' => "tmp-imports/{$filename}",
        'created_by' => $test->admin->id,
    ]);

    Storage::disk('local')->put($run->upload_path, file_get_contents(buildOpnameMonthlyReportXlsx($dates, $dataByDateBySku, $filename)));

    (new ProcessOpnameMonthlyImportJob($run->id))->handle(app(OpnameService::class));

    return $run->fresh();
}

test('a new date stores reported in/out/spoil alongside the balance that drives the stock ledger', function (): void {
    $run = runMonthlyImport($this, ['2026-11-01'], [
        '2026-11-01' => ['MKO-AJINOMOTO-500GR' => ['in' => '5', 'out' => '3', 'spoil' => '1', 'balance' => '20']],
    ], 'opname-reported-new.xlsx');

    expect($run->status)->toBe(MonthlyImportRun::STATUS_DONE)
        ->and($run->processed_units)->toBe(1)
        ->and($run->failed_units)->toBe(0);

    $session = OpnameSession::query()->where('outlet_id', $this->outlet->id)->whereDate('opname_date', '2026-11-01')->firstOrFail();
    expect($session->status)->toBe(OpnameSession::STATUS_PROCESSED);

    $opnameItem = OpnameItem::query()->where('opname_session_id', $session->id)->where('item_id', $this->item->id)->firstOrFail();
    expect((string) $opnameItem->reported_in_qty)->toBe('5.000000')
        ->and((string) $opnameItem->reported_out_qty)->toBe('3.000000')
        ->and((string) $opnameItem->reported_spoil_qty)->toBe('1.000000');

    $balance = StockBalance::query()
        ->where('item_id', $this->item->id)->where('outlet_id', $this->outlet->id)
        ->where('stock_target', StockMutation::TARGET_OUTLET_DAILY)->firstOrFail();
    expect((string) $balance->qty_on_hand)->toBe('20.000000');
});

test('re-uploading a processed date corrects reported in/out/spoil without touching the locked balance', function (): void {
    runMonthlyImport($this, ['2026-11-01'], [
        '2026-11-01' => ['MKO-AJINOMOTO-500GR' => ['in' => '5', 'out' => '3', 'spoil' => '1', 'balance' => '20']],
    ], 'opname-reported-initial.xlsx');

    $balanceAfterFirstRun = StockBalance::query()
        ->where('item_id', $this->item->id)->where('outlet_id', $this->outlet->id)
        ->where('stock_target', StockMutation::TARGET_OUTLET_DAILY)->firstOrFail()->qty_on_hand;

    // Upload kedua: outlet "kacau" di angka OUT sebelumnya (3), HQ betulkan
    // jadi 4 -- BAL juga dikirim beda (999, seolah salah ketik) TAPI harus
    // TETAP DIABAIKAN karena sesi tanggal ini sudah PROCESSED.
    $run = runMonthlyImport($this, ['2026-11-01'], [
        '2026-11-01' => ['MKO-AJINOMOTO-500GR' => ['in' => '5', 'out' => '4', 'spoil' => '1', 'balance' => '999']],
    ], 'opname-reported-correction.xlsx');

    expect($run->status)->toBe(MonthlyImportRun::STATUS_DONE)
        ->and($run->processed_units)->toBe(1)
        ->and($run->failed_units)->toBe(0);

    $session = OpnameSession::query()->where('outlet_id', $this->outlet->id)->whereDate('opname_date', '2026-11-01')->firstOrFail();
    expect($session->status)->toBe(OpnameSession::STATUS_PROCESSED);

    $opnameItem = OpnameItem::query()->where('opname_session_id', $session->id)->where('item_id', $this->item->id)->firstOrFail();
    expect((string) $opnameItem->reported_out_qty)->toBe('4.000000')
        ->and((string) $opnameItem->physical_qty_whole)->toBe('0.000000')
        ->and((string) $opnameItem->physical_qty_loose)->toBe('20.000000');

    $balanceAfterSecondRun = StockBalance::query()
        ->where('item_id', $this->item->id)->where('outlet_id', $this->outlet->id)
        ->where('stock_target', StockMutation::TARGET_OUTLET_DAILY)->firstOrFail()->qty_on_hand;
    expect((string) $balanceAfterSecondRun)->toBe((string) $balanceAfterFirstRun);
});

test('a date with no balance anywhere and no existing session is a no-op', function (): void {
    $run = runMonthlyImport($this, ['2026-11-05'], [
        '2026-11-05' => ['MKO-AJINOMOTO-500GR' => ['in' => '5', 'out' => '', 'spoil' => '', 'balance' => '']],
    ], 'opname-reported-noop.xlsx');

    expect($run->status)->toBe(MonthlyImportRun::STATUS_DONE)
        ->and($run->failed_units)->toBe(0);

    expect(OpnameSession::query()->where('outlet_id', $this->outlet->id)->whereDate('opname_date', '2026-11-05')->exists())->toBeFalse();
});
