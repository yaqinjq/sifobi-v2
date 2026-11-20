<?php

use App\Models\User;
use App\Modules\Core\Models\Brand;
use App\Modules\Core\Models\Outlet;
use App\Modules\Core\Models\Tenant;
use App\Modules\Production\Models\Menu;
use App\Modules\Production\Models\MenuCategory;
use Database\Seeders\MinimumMasterDataSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
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
    $this->admin = User::query()->where('email', 'admin@sifobi.test')->firstOrFail();
    $this->brand = Brand::query()->where('tenant_id', $this->tenant->id)->where('code', 'MYKOPIO')->firstOrFail();
});

/**
 * Meniru bentuk export "Master Menu" POS asli: judul di baris 1-4, header di
 * baris 6, data mulai baris 7.
 *
 * @param  list<array{0: string, 1: string, 2: string, 3: string}>  $rows  [code, name, category detail, status]
 */
function menuImportXlsx(array $rows, string $filename): string
{
    $path = storage_path('framework/testing/'.$filename);
    File::ensureDirectoryExists(dirname($path));

    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Report');
    $sheet->setCellValue('A1', 'Master Menu');
    $sheet->setCellValue('A2', 'CV. Multirasa Kopi Jaya 2');
    $sheet->setCellValue('A4', 'Generated');

    $sheet->fromArray(['Menu Id', 'Menu Code', 'Menu Name', 'Menu Short Name', 'Category Detail', 'Category', 'Status'], null, 'A6');

    foreach ($rows as $i => [$code, $name, $categoryDetail, $status]) {
        $sheet->fromArray([$i + 1, $code, $name, $name, $categoryDetail, 'BEVERAGE', $status], null, 'A'.(7 + $i));
    }

    (new Xlsx($spreadsheet))->save($path);

    return $path;
}

function menuImportUpload(string $path): UploadedFile
{
    return new UploadedFile($path, basename($path), 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
}

test('import membuat menu per brand, kategori otomatis, dan status nonaktif dihormati', function (): void {
    $path = menuImportXlsx([
        ['APB001', 'HOT KOPI O', 'CLAYPOT', 'Active'],
        ['APB002', 'ICE KOPI O', 'CLAYPOT', 'Inactive'],
        ['APF001', 'NASI LEMAK', 'RICE', 'Active'],
    ], 'menu-import-basic.xlsx');

    $this->actingAs($this->admin)
        ->post(route('production.menus.import'), ['brand_id' => $this->brand->id, 'file' => menuImportUpload($path)])
        ->assertRedirect(route('production.menus.index'))
        ->assertSessionHas('success');

    $menus = Menu::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->where('brand_id', $this->brand->id)->get()->keyBy('code');

    expect($menus)->toHaveCount(3)
        ->and($menus['APB001']->name)->toBe('HOT KOPI O')
        ->and($menus['APB001']->is_active)->toBeTrue()
        ->and($menus['APB002']->is_active)->toBeFalse()
        ->and($menus['APB001']->selling_price)->toBeNull()
        ->and($menus['APB001']->category?->name)->toBe('CLAYPOT')
        ->and($menus['APF001']->category?->name)->toBe('RICE');

    expect(MenuCategory::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->whereIn('code', ['CLAYPOT', 'RICE'])->count())->toBe(2);
});

test('import ulang file yang sama memperbarui menu, bukan membuat duplikat', function (): void {
    $path = menuImportXlsx([['APB001', 'HOT KOPI O', 'CLAYPOT', 'Active']], 'menu-import-twice.xlsx');

    $this->actingAs($this->admin)->post(route('production.menus.import'), ['brand_id' => $this->brand->id, 'file' => menuImportUpload($path)]);

    $path = menuImportXlsx([['APB001', 'HOT KOPI O SPESIAL', 'CLAYPOT', 'Active']], 'menu-import-twice.xlsx');

    $this->actingAs($this->admin)->post(route('production.menus.import'), ['brand_id' => $this->brand->id, 'file' => menuImportUpload($path)]);

    $menus = Menu::withoutGlobalScopes()->where('brand_id', $this->brand->id)->where('code', 'APB001')->get();

    expect($menus)->toHaveCount(1)
        ->and($menus->first()->name)->toBe('HOT KOPI O SPESIAL');
});

test('kode yang dipakai 2 menu berbeda di 1 file tidak menimpa menu pertama dan dilaporkan', function (): void {
    // Kasus nyata di export asli: APFSID047 dipakai AYAM CURRY GUNTING & CHICKEN CHOP CURRY.
    $path = menuImportXlsx([
        ['APFSID047', 'AYAM CURRY GUNTING', 'RICE', 'Active'],
        ['APFSID047', 'CHICKEN CHOP CURRY', 'RICE', 'Active'],
    ], 'menu-import-dup.xlsx');

    $this->actingAs($this->admin)
        ->post(route('production.menus.import'), ['brand_id' => $this->brand->id, 'file' => menuImportUpload($path)])
        ->assertSessionHas('import_errors', fn (array $errors) => count($errors) === 1 && str_contains($errors[0], 'CHICKEN CHOP CURRY'));

    $menus = Menu::withoutGlobalScopes()->where('brand_id', $this->brand->id)->where('code', 'APFSID047')->get();

    expect($menus)->toHaveCount(1)
        ->and($menus->first()->name)->toBe('AYAM CURRY GUNTING');
});

test('user tanpa manage_recipes tidak bisa import menu', function (): void {
    $outlet = Outlet::query()->where('code', 'MKO_OUTLET_1')->firstOrFail();
    $staff = User::factory()->create(['tenant_id' => $this->tenant->id, 'outlet_id' => $outlet->id, 'status' => 'ACTIVE']);
    $staff->assignRole('STAFF_BAR');

    $path = menuImportXlsx([['APB001', 'HOT KOPI O', 'CLAYPOT', 'Active']], 'menu-import-forbidden.xlsx');

    $this->actingAs($staff)
        ->post(route('production.menus.import'), ['brand_id' => $this->brand->id, 'file' => menuImportUpload($path)])
        ->assertForbidden();

    expect(Menu::withoutGlobalScopes()->where('code', 'APB001')->exists())->toBeFalse();
});

test('halaman form import bisa dibuka dan menampilkan jumlah outlet per brand', function (): void {
    $this->actingAs($this->admin)
        ->get(route('production.menus.import-form'))
        ->assertOk()
        ->assertSee('(1 outlet)', false);
});
