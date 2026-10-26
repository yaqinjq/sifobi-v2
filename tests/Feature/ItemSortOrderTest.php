<?php

use App\Imports\ItemSortOrderImport;
use App\Models\User;
use App\Modules\Core\Models\Department;
use App\Modules\Core\Models\Tenant;
use App\Modules\Inventory\Models\Item;
use App\Modules\Inventory\Models\ItemCategory;
use App\Modules\Inventory\Models\ItemDepartmentSortOrder;
use Database\Seeders\MinimumMasterDataSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed([
        RolesAndPermissionsSeeder::class,
        MinimumMasterDataSeeder::class,
    ]);

    $this->tenant = Tenant::query()->where('code', 'MKO')->firstOrFail();
    $this->bar = Department::query()->where('tenant_id', $this->tenant->id)->where('code', 'BAR')->firstOrFail();
    $this->ajinomoto = Item::query()->where('tenant_id', $this->tenant->id)->where('canonical_sku', 'MKO-AJINOMOTO-500GR')->firstOrFail();
    $this->gulaPasir = Item::query()->where('tenant_id', $this->tenant->id)->where('canonical_sku', 'MKO-GULA-PASIR')->firstOrFail();
    $this->susuUht = Item::query()->where('tenant_id', $this->tenant->id)->where('canonical_sku', 'MKO-SUSU-UHT')->firstOrFail();

    // Ajinomoto & Gula Pasir berbagi 1 sub kategori (bumbuCategory dari
    // seeder), Susu UHT di sub kategori lain -- cocok untuk menguji urutan
    // ANTAR item di dalam 1 sub kategori yang sama.
    $this->bumbuCategory = ItemCategory::query()->withoutGlobalScopes()->findOrFail($this->ajinomoto->item_category_id);

    foreach ([$this->ajinomoto, $this->gulaPasir, $this->susuUht] as $item) {
        $item->departments()->syncWithoutDetaching([$this->bar->id]);
    }

    $this->admin = User::query()->where('email', 'admin@sifobi.test')->firstOrFail();
});

test('item without an explicit sort order sorts after mapped items in the same sub category, alphabetically among themselves', function (): void {
    // Tanpa urutan eksplisit sama sekali: alfabetis (Ajinomoto sebelum Gula
    // Pasir) -- baseline sebelum ada mapping.
    expect($this->ajinomoto->formSortKey($this->bar->id))
        ->toBeLessThan($this->gulaPasir->formSortKey($this->bar->id));

    // Begitu Gula Pasir diberi urutan eksplisit (1), dia harus pindah ke
    // DEPAN Ajinomoto yang belum di-mapping -- bukan lagi alfabetis murni.
    ItemDepartmentSortOrder::query()->create([
        'item_id' => $this->gulaPasir->id,
        'department_id' => $this->bar->id,
        'sort_order' => 1,
    ]);

    $this->gulaPasir->unsetRelation('departmentSortOrders');

    expect($this->gulaPasir->formSortKey($this->bar->id))
        ->toBeLessThan($this->ajinomoto->formSortKey($this->bar->id));
});

test('sortOrderForDepartment returns null when unmapped and the stored value when mapped', function (): void {
    expect($this->ajinomoto->sortOrderForDepartment($this->bar->id))->toBeNull();

    ItemDepartmentSortOrder::query()->create([
        'item_id' => $this->ajinomoto->id,
        'department_id' => $this->bar->id,
        'sort_order' => 3,
    ]);

    $this->ajinomoto->unsetRelation('departmentSortOrders');

    expect($this->ajinomoto->sortOrderForDepartment($this->bar->id))->toBe(3);
});

test('import upserts sort order by SKU, skips blank rows, and errors on unknown SKU or non-numeric order', function (): void {
    $path = storage_path('framework/testing/item-sort-order-import.xlsx');
    File::ensureDirectoryExists(dirname($path));

    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->fromArray([
        ['SKU', 'Item', 'Kategori', 'Sub Kategori', 'Urutan'],
        ['MKO-AJINOMOTO-500GR', 'Ajinomoto 500gr', '-', $this->bumbuCategory->name, '2'],
        ['MKO-GULA-PASIR', 'Gula Pasir', '-', $this->bumbuCategory->name, ''], // dikosongkan, harus dilewati
        ['MKO-TIDAK-ADA', 'Item Hantu', '-', '-', '5'],
        ['MKO-SUSU-UHT', 'Susu UHT', '-', 'Milk', 'bukan-angka'],
    ]);
    (new Xlsx($spreadsheet))->save($path);

    $import = new ItemSortOrderImport($this->tenant->id, $this->bar->id);
    $import->import($path);
    $summary = $import->summary();

    expect($summary['processed'])->toBe(1)
        ->and($summary['failed'])->toBe(2)
        ->and($summary['errors'][0]['message'])->toContain('tidak ditemukan')
        ->and($summary['errors'][1]['message'])->toContain('angka bulat positif');

    expect(ItemDepartmentSortOrder::query()->where('item_id', $this->ajinomoto->id)->where('department_id', $this->bar->id)->value('sort_order'))->toBe(2)
        ->and(ItemDepartmentSortOrder::query()->where('item_id', $this->gulaPasir->id)->exists())->toBeFalse()
        ->and(ItemDepartmentSortOrder::query()->where('item_id', $this->susuUht->id)->exists())->toBeFalse();
});

test('re-importing the same SKU updates the existing row instead of creating a duplicate', function (): void {
    $path = storage_path('framework/testing/item-sort-order-reimport.xlsx');
    File::ensureDirectoryExists(dirname($path));

    $build = function (string $urutan) use ($path): void {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([
            ['SKU', 'Item', 'Kategori', 'Sub Kategori', 'Urutan'],
            ['MKO-AJINOMOTO-500GR', 'Ajinomoto 500gr', '-', $this->bumbuCategory->name, $urutan],
        ]);
        (new Xlsx($spreadsheet))->save($path);
    };

    $build('1');
    (new ItemSortOrderImport($this->tenant->id, $this->bar->id))->import($path);

    $build('4');
    (new ItemSortOrderImport($this->tenant->id, $this->bar->id))->import($path);

    expect(ItemDepartmentSortOrder::query()->where('item_id', $this->ajinomoto->id)->where('department_id', $this->bar->id)->count())->toBe(1)
        ->and(ItemDepartmentSortOrder::query()->where('item_id', $this->ajinomoto->id)->value('sort_order'))->toBe(4);
});

test('import form page renders for a permitted user', function (): void {
    $this->actingAs($this->admin)
        ->get(route('settings.item-sort-order.import-form'))
        ->assertOk()
        ->assertSee('Urutan Item per Departemen');
});

test('template download includes the department items pre-sorted and pre-filled with existing sort order', function (): void {
    ItemDepartmentSortOrder::query()->create([
        'item_id' => $this->gulaPasir->id,
        'department_id' => $this->bar->id,
        'sort_order' => 1,
    ]);

    $response = $this->actingAs($this->admin)
        ->get(route('settings.item-sort-order.template', ['department_id' => $this->bar->id]));

    $response->assertOk();

    $tmpPath = storage_path('framework/testing/item-sort-order-template-download.xlsx');
    File::ensureDirectoryExists(dirname($tmpPath));
    File::put($tmpPath, $response->streamedContent());

    $spreadsheet = IOFactory::load($tmpPath);
    $sheet = $spreadsheet->getSheetByName('URUTAN_ITEM');

    $rows = [];
    foreach (range(2, $sheet->getHighestDataRow()) as $r) {
        $rows[$sheet->getCell("A{$r}")->getValue()] = [
            'row' => $r,
            'urutan' => $sheet->getCell("E{$r}")->getValue(),
        ];
    }

    // Ajinomoto & Gula Pasir berbagi 1 sub kategori yang sama -- Gula Pasir
    // sudah di-mapping urutannya (1), jadi harus tampil SEBELUM Ajinomoto
    // yang belum di-mapping, meski namanya alfabetis duluan.
    expect($sheet->getCell('A1')->getValue())->toBe('SKU')
        ->and($rows)->toHaveKeys(['MKO-AJINOMOTO-500GR', 'MKO-GULA-PASIR', 'MKO-SUSU-UHT'])
        ->and($rows['MKO-GULA-PASIR']['row'])->toBeLessThan($rows['MKO-AJINOMOTO-500GR']['row'])
        ->and($rows['MKO-GULA-PASIR']['urutan'])->toBe(1)
        ->and($rows['MKO-AJINOMOTO-500GR']['urutan'])->toBeNull();
});

test('uploading a filled-in template via HTTP updates the sort order and reports success', function (): void {
    $path = storage_path('framework/testing/item-sort-order-upload.xlsx');
    File::ensureDirectoryExists(dirname($path));

    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->fromArray([
        ['SKU', 'Item', 'Kategori', 'Sub Kategori', 'Urutan'],
        ['MKO-AJINOMOTO-500GR', 'Ajinomoto 500gr', '-', $this->bumbuCategory->name, '2'],
        ['MKO-GULA-PASIR', 'Gula Pasir', '-', $this->bumbuCategory->name, '1'],
    ]);
    (new Xlsx($spreadsheet))->save($path);

    $file = new \Illuminate\Http\UploadedFile($path, 'urutan.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

    $this->actingAs($this->admin)
        ->post(route('settings.item-sort-order.import'), [
            'department_id' => $this->bar->id,
            'file' => $file,
        ])
        ->assertRedirect(route('settings.item-sort-order.import-form'))
        ->assertSessionHas('success');

    expect(ItemDepartmentSortOrder::query()->where('item_id', $this->gulaPasir->id)->where('department_id', $this->bar->id)->value('sort_order'))->toBe(1)
        ->and(ItemDepartmentSortOrder::query()->where('item_id', $this->ajinomoto->id)->where('department_id', $this->bar->id)->value('sort_order'))->toBe(2);
});
