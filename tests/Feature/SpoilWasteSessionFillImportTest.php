<?php

use App\Models\User;
use App\Modules\Core\Models\Department;
use App\Modules\Core\Models\Outlet;
use App\Modules\Core\Models\Tenant;
use App\Modules\Inventory\Models\Item;
use App\Modules\Operations\Models\SpoilWaste;
use Database\Seeders\MinimumMasterDataSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed([
        RolesAndPermissionsSeeder::class,
        MinimumMasterDataSeeder::class,
    ]);

    $this->tenant = Tenant::query()->where('code', 'MKO')->firstOrFail();
    $this->outlet = Outlet::query()->where('tenant_id', $this->tenant->id)->where('code', 'MKO_OUTLET_1')->firstOrFail();
    $this->bar = Department::query()->where('code', 'BAR')->firstOrFail();
    $this->item = Item::query()->where('canonical_sku', 'MKO-AJINOMOTO-500GR')->firstOrFail();
    $this->item->update(['primary_department_id' => $this->bar->id]);

    $this->staff = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'outlet_id' => $this->outlet->id,
        'department_id' => $this->bar->id,
        'status' => 'ACTIVE',
    ]);
    $this->staff->assignRole('STAFF_BAR');
});

/**
 * Mirror struktur SpoilGridWriter (header 1 baris: TGL|Kode Item|ITEM|Satuan|
 * STOK|PRICE/UNIT|TOTAL HARGA|Kategori Alasan|NOTE).
 *
 * @param  list<array{sku: string, item: string, stok?: string, kategori_alasan?: string, tgl?: string, note?: string}>  $rows
 */
function buildSpoilFillXlsx(array $rows, string $filename): string
{
    $path = storage_path('framework/testing/'.$filename);
    File::ensureDirectoryExists(dirname($path));

    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('ISI_SPOIL');

    $header = ['TGL', 'Kode Item', 'ITEM', 'Satuan', 'STOK', 'PRICE/UNIT', 'TOTAL HARGA', 'Kategori Alasan', 'NOTE'];
    foreach ($header as $col => $value) {
        $sheet->setCellValueExplicitByColumnAndRow($col + 1, 1, $value, DataType::TYPE_STRING);
    }

    $row = 2;
    foreach ($rows as $data) {
        $sheet->setCellValue("A{$row}", $data['tgl'] ?? '');
        $sheet->setCellValue("B{$row}", $data['sku']);
        $sheet->setCellValue("C{$row}", $data['item']);
        $sheet->setCellValue("E{$row}", $data['stok'] ?? '');
        $sheet->setCellValue("H{$row}", $data['kategori_alasan'] ?? '');
        $sheet->setCellValue("I{$row}", $data['note'] ?? '');
        $row++;
    }

    (new Xlsx($spreadsheet))->save($path);

    return $path;
}

function uploadSpoilFillFile(string $path): UploadedFile
{
    return new UploadedFile($path, basename($path), 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
}

test('template download returns xlsx for the staffs own outlet and department', function (): void {
    $response = $this->actingAs($this->staff)->get(route('operations.spoil-wastes.fill-template'));

    $response->assertOk();
    $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
});

test('the fill page renders for a staff member without a scope picker', function (): void {
    $this->actingAs($this->staff)
        ->get(route('operations.spoil-wastes.fill'))
        ->assertOk()
        ->assertViewHas('isSuperAdmin', false);
});

test('the fill page renders an outlet/department picker for super admin', function (): void {
    $admin = User::query()->where('email', 'admin@sifobi.test')->firstOrFail();

    $this->actingAs($admin)
        ->get(route('operations.spoil-wastes.fill'))
        ->assertOk()
        ->assertViewHas('isSuperAdmin', true);
});

test('upload creates pending spoil waste records and leaves blank rows untouched', function (): void {
    $path = buildSpoilFillXlsx([
        ['sku' => $this->item->canonical_sku, 'item' => $this->item->name, 'stok' => '5', 'kategori_alasan' => 'Rusak/Cacat', 'tgl' => '2026-11-10'],
    ], 'spoil-fill-apply.xlsx');

    $response = $this->actingAs($this->staff)->post(route('operations.spoil-wastes.fill-import'), [
        'file' => uploadSpoilFillFile($path),
    ]);

    $response->assertRedirect(route('operations.spoil-wastes.index'));
    $response->assertSessionHas('success');

    $spoil = SpoilWaste::query()->where('tenant_id', $this->tenant->id)->where('item_id', $this->item->id)->firstOrFail();
    expect($spoil->status)->toBe(SpoilWaste::STATUS_PENDING);
    expect($spoil->reason_category)->toBe(SpoilWaste::REASON_RUSAK);
    expect($spoil->recorded_date->toDateString())->toBe('2026-11-10');

    @unlink($path);
});

test('upload rejects a row missing kategori alasan without creating anything', function (): void {
    $path = buildSpoilFillXlsx([
        ['sku' => $this->item->canonical_sku, 'item' => $this->item->name, 'stok' => '5'],
    ], 'spoil-fill-no-reason.xlsx');

    $response = $this->actingAs($this->staff)->post(route('operations.spoil-wastes.fill-import'), [
        'file' => uploadSpoilFillFile($path),
    ]);

    $response->assertSessionHas('error');
    expect(SpoilWaste::query()->where('tenant_id', $this->tenant->id)->count())->toBe(0);

    @unlink($path);
});

test('upload reports unmatched sku without creating anything', function (): void {
    $path = buildSpoilFillXlsx([
        ['sku' => 'SKU-TIDAK-ADA', 'item' => 'Barang Fiktif', 'stok' => '5', 'kategori_alasan' => 'Rusak/Cacat'],
    ], 'spoil-fill-not-found.xlsx');

    $response = $this->actingAs($this->staff)->post(route('operations.spoil-wastes.fill-import'), [
        'file' => uploadSpoilFillFile($path),
    ]);

    $response->assertSessionHas('error');
    expect(SpoilWaste::query()->where('tenant_id', $this->tenant->id)->count())->toBe(0);

    @unlink($path);
});

test('a user without a valid spoil scope is redirected or forbidden', function (): void {
    $plainUser = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'outlet_id' => null,
        'department_id' => null,
        'status' => 'ACTIVE',
    ]);
    $plainUser->assignRole('STAFF_BAR');

    $this->actingAs($plainUser)->get(route('operations.spoil-wastes.fill-template'))->assertForbidden();
});
