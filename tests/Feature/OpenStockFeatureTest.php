<?php

use App\Models\User;
use App\Modules\Core\Models\Department;
use App\Modules\Core\Models\Outlet;
use App\Modules\Core\Models\Tenant;
use App\Modules\Inventory\Models\Item;
use App\Modules\Operations\Models\OpenStock;
use App\Modules\Stock\Models\StockBalance;
use App\Modules\Stock\Models\StockMutation;
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

    /** @phpstan-ignore-next-line */
    $this->tenant = Tenant::query()->where('code', 'MKO')->firstOrFail();
    /** @phpstan-ignore-next-line */
    $this->outlet = Outlet::query()->where('code', 'MKO_OUTLET_1')->firstOrFail();
    /** @phpstan-ignore-next-line */
    $this->item   = Item::query()->where('canonical_sku', 'MKO-AJINOMOTO-500GR')->firstOrFail();
});

function openStockUser(string $roleName): User
{
    $tenant = Tenant::query()->where('code', 'MKO')->firstOrFail();

    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'name'      => $roleName.' User',
        'email'     => strtolower($roleName).'@sifobi.test',
        'status'    => 'ACTIVE',
    ]);

    $user->assignRole($roleName);

    return $user;
}

/**
 * Bulk payload — wraps a single item into the new array format.
 * Item-level overrides: item_id, qty_whole, qty_loose, cost_per_unit, notes.
 * Outer overrides: outlet_id, stock_target, business_date.
 *
 * @param  array<string, mixed>  $itemOverrides
 * @param  array<string, mixed>  $outerOverrides
 * @return array<string, mixed>
 */
function openStockPayload(array $itemOverrides = [], array $outerOverrides = []): array
{
    $outlet = Outlet::query()->where('code', 'MKO_OUTLET_1')->firstOrFail();
    $item   = Item::query()->where('canonical_sku', 'MKO-AJINOMOTO-500GR')->firstOrFail();
    $department = Department::query()->where('code', 'BAR')->firstOrFail();

    return array_merge([
        'outlet_id'     => $outlet->id,
        'stock_target'  => OpenStock::TARGET_OUTLET_DAILY,
        'business_date' => '2026-06-28',
        'items'         => [
            array_merge([
                'item_id'      => $item->id,
                'department_id'=> $department->id,
                'qty_whole'    => '2',
                'qty_loose'    => '50',
                'cost_per_unit'=> '12000',
                'notes'        => 'Initial baseline',
            ], $itemOverrides),
        ],
    ], $outerOverrides);
}

// ─── Access control ──────────────────────────────────────────────────────────

test('guest cannot access open stock', function (): void {
    $this->get('/operations/open-stocks')
        ->assertRedirect('/login');
});

test('user without permission cannot create open stock', function (): void {
    $user = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'status'    => 'ACTIVE',
    ]);

    $this->actingAs($user)
        ->get('/operations/open-stocks/create')
        ->assertForbidden();
});

// ─── Draft CRUD ───────────────────────────────────────────────────────────────

test('user with input_open_stock can create draft via bulk form', function (): void {
    $user = openStockUser('STAFF_BAR');

    $this->actingAs($user)
        ->post('/operations/open-stocks', openStockPayload(['qty_whole' => '1,5', 'qty_loose' => '0']))
        ->assertRedirect();

    $this->assertDatabaseHas('open_stocks', [
        'tenant_id'      => $this->tenant->id,
        'outlet_id'      => $this->outlet->id,
        'item_id'        => $this->item->id,
        'status'         => OpenStock::STATUS_DRAFT,
        'qty_whole'      => '1.500000',
        'qty_in_base_unit' => '750.000000',
    ]);
});

test('bulk store creates multiple drafts in one transaction', function (): void {
    $user   = openStockUser('STAFF_BAR');
    $item2  = Item::query()->where('canonical_sku', 'MKO-GULA-PASIR')->firstOrFail();
    $department = Department::query()->where('code', 'BAR')->firstOrFail();

    $payload = [
        'outlet_id'     => $this->outlet->id,
        'stock_target'  => OpenStock::TARGET_OUTLET_DAILY,
        'business_date' => '2026-06-28',
        'items'         => [
            ['department_id' => $department->id, 'item_id' => $this->item->id, 'qty_whole' => '2', 'qty_loose' => '50'],
            ['department_id' => $department->id, 'item_id' => $item2->id, 'qty_whole' => '5', 'qty_loose' => '0'],
        ],
    ];

    $this->actingAs($user)
        ->post('/operations/open-stocks', $payload)
        ->assertRedirect(route('operations.open-stocks.index'));

    expect(OpenStock::query()->count())->toBe(2);
});

test('bulk store rejects duplicate item_ids in one batch', function (): void {
    $user = openStockUser('STAFF_BAR');
    $department = Department::query()->where('code', 'BAR')->firstOrFail();

    $payload = [
        'outlet_id'     => $this->outlet->id,
        'stock_target'  => OpenStock::TARGET_OUTLET_DAILY,
        'business_date' => '2026-06-28',
        'items'         => [
            ['department_id' => $department->id, 'item_id' => $this->item->id, 'qty_whole' => '1', 'qty_loose' => '0'],
            ['department_id' => $department->id, 'item_id' => $this->item->id, 'qty_whole' => '2', 'qty_loose' => '0'],
        ],
    ];

    $this->actingAs($user)
        ->from('/operations/open-stocks/create')
        ->post('/operations/open-stocks', $payload)
        ->assertSessionHasErrors('items');

    expect(OpenStock::query()->count())->toBe(0);
});

test('create page shows batch row controls', function (): void {
    $user = openStockUser('STAFF_BAR');

    $this->actingAs($user)
        ->get(route('operations.open-stocks.create'))
        ->assertOk()
        ->assertSee('Header Batch')
        ->assertSee('+ Tambah 5 Baris')
        ->assertSee('Simpan Batch Draft');
});

test('item search returns unit ids and purchase ratio for batch form', function (): void {
    $user = openStockUser('STAFF_BAR');

    $this->actingAs($user)
        ->getJson(route('operations.open-stocks.item-search', ['q' => 'aji']))
        ->assertOk()
        ->assertJsonFragment(['sku' => 'MKO-AJINOMOTO-500GR'])
        ->assertJsonStructure([
            '*' => [
                'id',
                'name',
                'sku',
                'base_unit',
                'base_unit_id',
                'inventory_unit',
                'inventory_unit_id',
                'purchase_unit',
                'purchase_unit_id',
                'inventory_ratio',
                'purchase_ratio',
            ],
        ]);
});

test('json batch store creates drafts and returns redirect payload', function (): void {
    $user = openStockUser('STAFF_BAR');
    $item2 = Item::query()->where('canonical_sku', 'MKO-GULA-PASIR')->firstOrFail();
    $department = Department::query()->where('code', 'BAR')->firstOrFail();

    $this->actingAs($user)
        ->postJson(route('operations.open-stocks.store'), [
            'outlet_id' => $this->outlet->id,
            'business_date' => '2026-06-29',
            'stock_target' => OpenStock::TARGET_OUTLET_DAILY,
            'batch_notes' => 'Batch dari test',
            'items' => [
                ['department_id' => $department->id, 'item_id' => $this->item->id, 'qty_whole' => '1', 'qty_loose' => '50'],
                ['department_id' => $department->id, 'item_id' => $item2->id, 'qty_whole' => '2', 'qty_loose' => '0'],
            ],
        ])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('count', 2);

    expect(OpenStock::query()->whereDate('business_date', '2026-06-29')->count())->toBe(2);
});

test('batch row can create daily and warehouse drafts for the same item', function (): void {
    $user = openStockUser('STAFF_BAR');
    $department = Department::query()->where('code', 'BAR')->firstOrFail();

    $this->actingAs($user)
        ->postJson(route('operations.open-stocks.store'), [
            'outlet_id' => $this->outlet->id,
            'business_date' => '2026-06-29',
            'stock_target' => OpenStock::TARGET_OUTLET_DAILY,
            'items' => [
                [
                    'department_id' => $department->id,
                    'item_id' => $this->item->id,
                    'targets' => [
                        OpenStock::TARGET_OUTLET_DAILY,
                        OpenStock::TARGET_OUTLET_WAREHOUSE,
                    ],
                    'qty_whole' => '1',
                    'qty_loose' => '50',
                    'qty_purchase' => '2',
                ],
            ],
        ])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('count', 2);

    $daily = OpenStock::query()
        ->where('item_id', $this->item->id)
        ->where('stock_target', OpenStock::TARGET_OUTLET_DAILY)
        ->firstOrFail();

    $warehouse = OpenStock::query()
        ->where('item_id', $this->item->id)
        ->where('stock_target', OpenStock::TARGET_OUTLET_WAREHOUSE)
        ->firstOrFail();

    expect((string) $daily->qty_whole)->toBe('1.000000')
        ->and((string) $daily->qty_loose)->toBe('50.000000')
        ->and((string) $daily->qty_in_base_unit)->toBe('550.000000')
        ->and((string) $warehouse->qty_whole)->toBe('2.000000')
        ->and((string) $warehouse->qty_loose)->toBe('0.000000')
        ->and((string) $warehouse->qty_in_base_unit)->toBe('24000.000000');
});

test('batch row can create warehouse only draft for an item', function (): void {
    $user = openStockUser('STAFF_BAR');
    $department = Department::query()->where('code', 'BAR')->firstOrFail();

    $this->actingAs($user)
        ->postJson(route('operations.open-stocks.store'), [
            'outlet_id' => $this->outlet->id,
            'business_date' => '2026-06-29',
            'stock_target' => OpenStock::TARGET_OUTLET_DAILY,
            'items' => [
                [
                    'department_id' => $department->id,
                    'item_id' => $this->item->id,
                    'targets' => [OpenStock::TARGET_OUTLET_WAREHOUSE],
                    'qty_whole' => '0',
                    'qty_loose' => '0',
                    'qty_purchase' => '3',
                ],
            ],
        ])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('count', 1);

    expect(OpenStock::query()->where('item_id', $this->item->id)->count())->toBe(1)
        ->and(OpenStock::query()->where('stock_target', OpenStock::TARGET_OUTLET_WAREHOUSE)->exists())->toBeTrue()
        ->and(OpenStock::query()->where('stock_target', OpenStock::TARGET_OUTLET_DAILY)->exists())->toBeFalse();
});

test('desktop open stock index shows create and import actions', function (): void {
    $user = openStockUser('STAFF_BAR');
    $this->actingAs($user)->post('/operations/open-stocks', openStockPayload());

    $this->actingAs($user)
        ->get(route('operations.open-stocks.index'))
        ->assertOk()
        ->assertSee('+ Input Stok Awal')
        ->assertSee('Import Excel');
});

test('open stock import template can be downloaded', function (): void {
    $user = openStockUser('STAFF_BAR');

    $this->actingAs($user)
        ->get(route('operations.open-stocks.import.template'))
        ->assertOk()
        ->assertHeader('content-disposition');
});

test('open stock import creates draft rows from excel', function (): void {
    $user = openStockUser('STAFF_BAR');
    $path = storage_path('framework/testing/open-stock-import.xlsx');
    File::ensureDirectoryExists(dirname($path));

    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->fromArray([
        ['tanggal_stok_awal', 'item_sku', 'departemen_code', 'target', 'qty_whole', 'qty_loose', 'catatan'],
        ['2026-06-29', 'MKO-AJINOMOTO-500GR', 'BAR', 'STOK_HARIAN_OUTLET', '2', '350', 'Sisa test'],
    ]);
    (new Xlsx($spreadsheet))->save($path);

    $upload = new UploadedFile(
        $path,
        'open-stock-import.xlsx',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        null,
        true
    );

    $this->actingAs($user)
        ->post(route('operations.open-stocks.import.store'), [
            'outlet_id' => $this->outlet->id,
            'file' => $upload,
        ])
        ->assertRedirect()
        ->assertSessionHas('import_result');

    $openStock = OpenStock::query()
        ->where('tenant_id', $this->tenant->id)
        ->where('outlet_id', $this->outlet->id)
        ->where('item_id', $this->item->id)
        ->where('stock_target', OpenStock::TARGET_OUTLET_DAILY)
        ->firstOrFail();

    expect($openStock->business_date->toDateString())->toBe('2026-06-29')
        ->and((string) $openStock->qty_whole)->toBe('2.000000')
        ->and((string) $openStock->qty_loose)->toBe('350.000000')
        ->and($openStock->status)->toBe(OpenStock::STATUS_DRAFT);
});

test('draft can be edited', function (): void {
    $user = openStockUser('STAFF_BAR');
    $this->actingAs($user)->post('/operations/open-stocks', openStockPayload());
    $openStock = OpenStock::query()->firstOrFail();

    $this->actingAs($user)
        ->put("/operations/open-stocks/{$openStock->id}", array_merge(
            openStockPayload(['qty_whole' => '3', 'qty_loose' => '25'])['items'][0],
            [
                'outlet_id'     => $this->outlet->id,
                'stock_target'  => OpenStock::TARGET_OUTLET_DAILY,
                'business_date' => '2026-06-28',
            ]
        ))
        ->assertRedirect();

    $openStock->refresh();

    expect((string) $openStock->qty_whole)->toBe('3.000000')
        ->and((string) $openStock->qty_loose)->toBe('25.000000')
        ->and((string) $openStock->qty_in_base_unit)->toBe('1525.000000');
});

test('draft can be deleted', function (): void {
    $user = openStockUser('STAFF_BAR');
    $this->actingAs($user)->post('/operations/open-stocks', openStockPayload());
    $openStock = OpenStock::query()->firstOrFail();

    $this->actingAs($user)
        ->delete("/operations/open-stocks/{$openStock->id}")
        ->assertRedirect('/operations/open-stocks');

    expect(OpenStock::query()->count())->toBe(0);
});

// ─── Post ────────────────────────────────────────────────────────────────────

test('draft can be posted by user with post_open_stock', function (): void {
    $user = openStockUser('PIC_OUTLET');
    $this->actingAs($user)->post('/operations/open-stocks', openStockPayload());
    $openStock = OpenStock::query()->firstOrFail();

    $this->actingAs($user)
        ->post("/operations/open-stocks/{$openStock->id}/post")
        ->assertRedirect();

    $openStock->refresh();

    expect($openStock->status)->toBe(OpenStock::STATUS_POSTED)
        ->and((string) $openStock->qty_in_base_unit)->toBe('1050.000000')
        ->and($openStock->mutation_id)->not->toBeNull();
});

test('posting creates OPEN_STOCK stock mutation', function (): void {
    $user = openStockUser('PIC_OUTLET');
    $this->actingAs($user)->post('/operations/open-stocks', openStockPayload());
    $openStock = OpenStock::query()->firstOrFail();

    $this->actingAs($user)->post("/operations/open-stocks/{$openStock->id}/post");

    $this->assertDatabaseHas('stock_mutations', [
        'tenant_id'     => $this->tenant->id,
        'outlet_id'     => $this->outlet->id,
        'item_id'       => $this->item->id,
        'stock_target'  => OpenStock::TARGET_OUTLET_DAILY,
        'mutation_type' => StockMutation::TYPE_OPEN_STOCK,
        'qty_change'    => '1050.000000',
    ]);
});

test('posting updates stock balance', function (): void {
    $user = openStockUser('PIC_OUTLET');
    $this->actingAs($user)->post('/operations/open-stocks', openStockPayload());
    $openStock = OpenStock::query()->firstOrFail();

    $this->actingAs($user)->post("/operations/open-stocks/{$openStock->id}/post");

    $balance = StockBalance::query()
        ->where('tenant_id', $this->tenant->id)
        ->where('outlet_id', $this->outlet->id)
        ->where('item_id', $this->item->id)
        ->where('stock_target', OpenStock::TARGET_OUTLET_DAILY)
        ->firstOrFail();

    expect((string) $balance->qty_on_hand)->toBe('1050.000000');
});

test('posted open stock cannot be edited', function (): void {
    $user = openStockUser('PIC_OUTLET');
    $this->actingAs($user)->post('/operations/open-stocks', openStockPayload());
    $openStock = OpenStock::query()->firstOrFail();
    $this->actingAs($user)->post("/operations/open-stocks/{$openStock->id}/post");

    $this->actingAs($user)
        ->from("/operations/open-stocks/{$openStock->id}")
        ->put("/operations/open-stocks/{$openStock->id}", array_merge(
            openStockPayload(['qty_whole' => '9'])['items'][0],
            ['outlet_id' => $this->outlet->id, 'stock_target' => OpenStock::TARGET_OUTLET_DAILY, 'business_date' => '2026-06-28']
        ))
        ->assertSessionHasErrors('status');

    expect((string) $openStock->refresh()->qty_in_base_unit)->toBe('1050.000000');
});

test('posted open stock cannot be deleted', function (): void {
    $user = openStockUser('PIC_OUTLET');
    $this->actingAs($user)->post('/operations/open-stocks', openStockPayload());
    $openStock = OpenStock::query()->firstOrFail();
    $this->actingAs($user)->post("/operations/open-stocks/{$openStock->id}/post");

    $this->actingAs($user)
        ->from("/operations/open-stocks/{$openStock->id}")
        ->delete("/operations/open-stocks/{$openStock->id}")
        ->assertSessionHasErrors('status');

    expect(OpenStock::query()->count())->toBe(1);
});

test('same tenant outlet item target and date cannot be posted twice', function (): void {
    $user = openStockUser('PIC_OUTLET');

    $this->actingAs($user)->post('/operations/open-stocks', openStockPayload());
    $first = OpenStock::query()->firstOrFail();
    $this->actingAs($user)->post("/operations/open-stocks/{$first->id}/post");

    $this->actingAs($user)->post('/operations/open-stocks', openStockPayload(['qty_loose' => '25']));
    $second = OpenStock::query()->where('status', OpenStock::STATUS_DRAFT)->firstOrFail();

    $this->actingAs($user)
        ->from("/operations/open-stocks/{$second->id}")
        ->post("/operations/open-stocks/{$second->id}/post")
        ->assertSessionHasErrors('item_id');
});

// ─── Void ────────────────────────────────────────────────────────────────────

test('posted open stock can be voided', function (): void {
    $user = openStockUser('PIC_OUTLET');
    $this->actingAs($user)->post('/operations/open-stocks', openStockPayload());
    $openStock = OpenStock::query()->firstOrFail();
    $this->actingAs($user)->post("/operations/open-stocks/{$openStock->id}/post");

    $this->actingAs($user)
        ->post("/operations/open-stocks/{$openStock->id}/void", ['reason' => 'Salah input qty'])
        ->assertRedirect();

    $openStock->refresh();

    expect($openStock->status)->toBe(OpenStock::STATUS_VOID)
        ->and($openStock->void_reason)->toBe('Salah input qty')
        ->and($openStock->voided_by)->toBe($user->id);
});

test('voiding creates VOID_REVERSAL mutation and zeroes balance', function (): void {
    $user = openStockUser('PIC_OUTLET');
    $this->actingAs($user)->post('/operations/open-stocks', openStockPayload());
    $openStock = OpenStock::query()->firstOrFail();
    $this->actingAs($user)->post("/operations/open-stocks/{$openStock->id}/post");

    $this->actingAs($user)
        ->post("/operations/open-stocks/{$openStock->id}/void", ['reason' => 'Salah input qty']);

    $this->assertDatabaseHas('stock_mutations', [
        'tenant_id'     => $this->tenant->id,
        'item_id'       => $this->item->id,
        'mutation_type' => StockMutation::TYPE_VOID_REVERSAL,
        'qty_change'    => '-1050.000000',
    ]);

    $balance = StockBalance::query()
        ->where('item_id', $this->item->id)
        ->where('outlet_id', $this->outlet->id)
        ->firstOrFail();

    expect((string) $balance->qty_on_hand)->toBe('0.000000');
});

test('void requires reason of at least 5 characters', function (): void {
    $user = openStockUser('PIC_OUTLET');
    $this->actingAs($user)->post('/operations/open-stocks', openStockPayload());
    $openStock = OpenStock::query()->firstOrFail();
    $this->actingAs($user)->post("/operations/open-stocks/{$openStock->id}/post");

    $this->actingAs($user)
        ->from("/operations/open-stocks/{$openStock->id}")
        ->post("/operations/open-stocks/{$openStock->id}/void", ['reason' => 'ok'])
        ->assertSessionHasErrors('reason');

    expect($openStock->refresh()->status)->toBe(OpenStock::STATUS_POSTED);
});

test('void cannot be applied to a DRAFT', function (): void {
    $user = openStockUser('PIC_OUTLET');
    $this->actingAs($user)->post('/operations/open-stocks', openStockPayload());
    $openStock = OpenStock::query()->firstOrFail();

    $this->actingAs($user)
        ->from("/operations/open-stocks/{$openStock->id}")
        ->post("/operations/open-stocks/{$openStock->id}/void", ['reason' => 'Salah input qty'])
        ->assertSessionHasErrors('status');

    expect($openStock->refresh()->status)->toBe(OpenStock::STATUS_DRAFT);
});

test('VOID_REVERSAL mutation cannot itself be voided', function (): void {
    $user = openStockUser('PIC_OUTLET');
    $this->actingAs($user)->post('/operations/open-stocks', openStockPayload());
    $openStock = OpenStock::query()->firstOrFail();
    $this->actingAs($user)->post("/operations/open-stocks/{$openStock->id}/post");
    $this->actingAs($user)->post("/operations/open-stocks/{$openStock->id}/void", ['reason' => 'Salah input qty']);

    $openStock->refresh();
    expect($openStock->status)->toBe(OpenStock::STATUS_VOID);

    // Trying to void again should fail
    $this->actingAs($user)
        ->from("/operations/open-stocks/{$openStock->id}")
        ->post("/operations/open-stocks/{$openStock->id}/void", ['reason' => 'Double void attempt'])
        ->assertSessionHasErrors('status');
});

// ─── Decimal formatting ───────────────────────────────────────────────────────

test('decimal comma is accepted', function (): void {
    $user = openStockUser('STAFF_BAR');

    $this->actingAs($user)
        ->post('/operations/open-stocks', openStockPayload(['qty_whole' => '1,5', 'qty_loose' => '0,25']))
        ->assertRedirect();

    $openStock = OpenStock::query()->firstOrFail();

    expect((string) $openStock->qty_whole)->toBe('1.500000')
        ->and((string) $openStock->qty_loose)->toBe('0.250000');
});

test('decimal dot is accepted', function (): void {
    $user = openStockUser('STAFF_BAR');

    $this->actingAs($user)
        ->post('/operations/open-stocks', openStockPayload(['qty_whole' => '1.5', 'qty_loose' => '0.25']))
        ->assertRedirect();

    $openStock = OpenStock::query()->firstOrFail();

    expect((string) $openStock->qty_whole)->toBe('1.500000')
        ->and((string) $openStock->qty_loose)->toBe('0.250000');
});

test('ambiguous decimal with dot then comma is rejected', function (): void {
    $user = openStockUser('STAFF_BAR');

    $this->actingAs($user)
        ->from('/operations/open-stocks/create')
        ->post('/operations/open-stocks', openStockPayload(['qty_whole' => '1.000,50']))
        ->assertSessionHasErrors('items.0.qty_whole');
});

test('ambiguous decimal with comma then dot is rejected', function (): void {
    $user = openStockUser('STAFF_BAR');

    $this->actingAs($user)
        ->from('/operations/open-stocks/create')
        ->post('/operations/open-stocks', openStockPayload(['qty_whole' => '1,000.50']))
        ->assertSessionHasErrors('items.0.qty_whole');
});
