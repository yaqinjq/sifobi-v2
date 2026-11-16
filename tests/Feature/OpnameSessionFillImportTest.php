<?php

use App\Models\User;
use App\Modules\Core\Models\Department;
use App\Modules\Core\Models\Outlet;
use App\Modules\Core\Models\Tenant;
use App\Modules\Inventory\Models\Item;
use App\Modules\Operations\Models\OpnameItem;
use App\Services\OpnameService;
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

    $this->admin = User::query()->where('email', 'admin@sifobi.test')->firstOrFail();
    $this->tenant = Tenant::query()->where('code', 'MKO')->firstOrFail();
    $this->outlet = Outlet::query()->where('code', 'MKO_OUTLET_1')->firstOrFail();
    $this->bar = Department::query()->where('code', 'BAR')->firstOrFail();
    $this->item = Item::query()->where('canonical_sku', 'MKO-AJINOMOTO-500GR')->firstOrFail();
    $this->item->update(['primary_department_id' => $this->bar->id]);

    $this->session = app(OpnameService::class)->startSession([
        'tenant_id' => $this->tenant->id,
        'outlet_id' => $this->outlet->id,
        'department_id' => $this->bar->id,
        'opname_date' => '2026-11-10',
        'type' => \App\Modules\Operations\Models\OpnameSession::TYPE_DAILY,
    ], $this->admin->id);
});

/**
 * Mirror struktur OpnameGridWriter (header 2 baris, kolom No|Kode Item|ITEM|
 * Satuan|SET OF(Ml|Gr|Pcs)|GUDANG(Balance)|AREA(Utuh|Ecer|Total)|TOTAL(Per
 * Satuan)|Departemen) -- importer baca heading row ke-2, data mulai row 3.
 *
 * @param  list<array{item: string, sku: string, departemen?: string, fisik_utuh?: string, fisik_ecer?: string}>  $rows
 */
function buildOpnameFillXlsx(array $rows, string $filename): string
{
    $path = storage_path('framework/testing/'.$filename);
    File::ensureDirectoryExists(dirname($path));

    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('ISI_OPNAME');

    $headerRow2 = ['A' => 'No', 'B' => 'Kode Item', 'C' => 'ITEM', 'D' => 'Satuan', 'I' => 'Utuh', 'J' => 'Ecer', 'K' => 'Total', 'M' => 'Departemen'];
    foreach ($headerRow2 as $col => $value) {
        $sheet->setCellValueExplicit("{$col}2", $value, DataType::TYPE_STRING);
    }

    $row = 3;
    foreach ($rows as $data) {
        $sheet->setCellValue("B{$row}", $data['sku']);
        $sheet->setCellValue("C{$row}", $data['item']);
        $sheet->setCellValue("M{$row}", $data['departemen'] ?? '');
        $sheet->setCellValue("I{$row}", $data['fisik_utuh'] ?? '');
        $sheet->setCellValue("J{$row}", $data['fisik_ecer'] ?? '');
        $row++;
    }

    (new Xlsx($spreadsheet))->save($path);

    return $path;
}

function uploadOpnameFillFile(string $path): UploadedFile
{
    return new UploadedFile($path, basename($path), 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
}

test('template download returns xlsx matching the sessions actual items', function (): void {
    $response = $this->actingAs($this->admin)->get(route('operations.opname.fill-template', $this->session));

    $response->assertOk();
    $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
});

test('upload applies qty to matched items via updateItem and leaves blank rows untouched', function (): void {
    $path = buildOpnameFillXlsx([
        ['item' => $this->item->name, 'sku' => $this->item->canonical_sku, 'departemen' => $this->bar->name, 'fisik_utuh' => '5', 'fisik_ecer' => '0'],
    ], 'opname-fill-apply.xlsx');

    $response = $this->actingAs($this->admin)->post(route('operations.opname.fill-import', $this->session), [
        'file' => uploadOpnameFillFile($path),
    ]);

    $response->assertRedirect(route('operations.opname.show', $this->session));
    $response->assertSessionHas('success');

    $opnameItem = OpnameItem::query()->where('opname_session_id', $this->session->id)->where('item_id', $this->item->id)->firstOrFail();
    expect($opnameItem->is_counted)->toBeTrue();
    expect((string) $opnameItem->physical_qty_whole)->toBe('5.000000');

    @unlink($path);
});

test('upload reports unmatched sku without applying anything', function (): void {
    $path = buildOpnameFillXlsx([
        ['item' => 'Barang Fiktif', 'sku' => 'SKU-TIDAK-ADA', 'departemen' => $this->bar->name, 'fisik_utuh' => '5', 'fisik_ecer' => '0'],
    ], 'opname-fill-not-found.xlsx');

    $response = $this->actingAs($this->admin)->post(route('operations.opname.fill-import', $this->session), [
        'file' => uploadOpnameFillFile($path),
    ]);

    $response->assertRedirect(route('operations.opname.show', $this->session));
    $response->assertSessionHas('error');

    expect(OpnameItem::query()->where('opname_session_id', $this->session->id)->where('is_counted', true)->count())->toBe(0);

    @unlink($path);
});

test('upload rejects non-draft session', function (): void {
    $this->session->fresh(['items'])->items->each(fn ($i) => app(OpnameService::class)->updateItem($i, '1', '0'));
    app(OpnameService::class)->submit($this->session->fresh(['items']), $this->admin->id);

    $path = buildOpnameFillXlsx([
        ['item' => $this->item->name, 'sku' => $this->item->canonical_sku, 'departemen' => $this->bar->name, 'fisik_utuh' => '5', 'fisik_ecer' => '0'],
    ], 'opname-fill-non-draft.xlsx');

    $response = $this->actingAs($this->admin)->post(route('operations.opname.fill-import', $this->session), [
        'file' => uploadOpnameFillFile($path),
    ]);

    $response->assertSessionHasErrors('status');

    @unlink($path);
});

test('a user cannot download or upload template for another outlets session', function (): void {
    $otherOutlet = Outlet::query()->where('tenant_id', $this->tenant->id)->where('code', 'MKO_OUTLET_2')->firstOrFail();
    $user = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'outlet_id' => $otherOutlet->id,
        'department_id' => $this->bar->id,
        'status' => 'ACTIVE',
    ]);
    $user->assignRole('PIC_OUTLET');
    $user->outlets()->sync([$otherOutlet->id]);

    $this->actingAs($user)->get(route('operations.opname.fill-template', $this->session))->assertForbidden();

    $path = buildOpnameFillXlsx([
        ['item' => $this->item->name, 'sku' => $this->item->canonical_sku, 'departemen' => $this->bar->name, 'fisik_utuh' => '5', 'fisik_ecer' => '0'],
    ], 'opname-fill-forbidden.xlsx');

    $this->actingAs($user)->post(route('operations.opname.fill-import', $this->session), [
        'file' => uploadOpnameFillFile($path),
    ])->assertForbidden();

    @unlink($path);
});
