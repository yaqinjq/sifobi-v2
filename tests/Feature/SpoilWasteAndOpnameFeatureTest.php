<?php

use App\Models\User;
use App\Modules\Core\Models\Department;
use App\Modules\Core\Models\Outlet;
use App\Modules\Core\Models\Tenant;
use App\Modules\Inventory\Models\Item;
use App\Modules\Operations\Models\OpnameSession;
use App\Modules\Operations\Models\SpoilWaste;
use App\Modules\Stock\Models\StockBalance;
use App\Modules\Stock\Models\StockMutation;
use App\Services\OpnameService;
use App\Services\SpoilWasteService;
use App\Services\StockLedgerService;
use Database\Seeders\MinimumMasterDataSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

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
    $this->department = Department::query()->where('code', 'BAR')->firstOrFail();
    /** @phpstan-ignore-next-line */
    $this->item = Item::query()->where('canonical_sku', 'MKO-AJINOMOTO-500GR')->firstOrFail();
    // Item harus terikat ke departemen BAR supaya lolos validasi item_id
    // per-departemen di SpoilWasteController (lihat
    // SpoilWasteDepartmentScopingFeatureTest) -- staff di test ini semua
    // STAFF_BAR.
    $this->item->update(['primary_department_id' => $this->department->id]);
    $this->unit = $this->item->inventory_unit_id;
    $this->baseUnit = $this->item->base_unit_id;
});

function operationUser(string $roleName): User
{
    $tenant = Tenant::query()->where('code', 'MKO')->firstOrFail();
    $outlet = Outlet::query()->where('code', 'MKO_OUTLET_1')->firstOrFail();
    $department = Department::query()->where('code', 'BAR')->firstOrFail();

    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'outlet_id' => $outlet->id,
        'department_id' => $department->id,
        'name' => $roleName.' Operations User',
        'email' => strtolower($roleName).'_ops@sifobi.test',
        'status' => 'ACTIVE',
    ]);

    $user->assignRole($roleName);

    return $user;
}

function seedDailyBalance(object $test, string $qty = '5000'): void
{
    app(StockLedgerService::class)->openStock([
        'tenant_id' => $test->tenant->id,
        'outlet_id' => $test->outlet->id,
        'item_id' => $test->item->id,
        'unit_id' => $test->baseUnit,
        'stock_target' => StockMutation::TARGET_OUTLET_DAILY,
        'qty' => $qty,
        'performed_by' => User::query()->where('email', 'admin@sifobi.test')->value('id'),
    ]);
}

test('guest cannot access spoil and opname pages', function (): void {
    $this->get(route('operations.spoil-wastes.index'))->assertRedirect('/login');
    $this->get(route('operations.opname.index'))->assertRedirect('/login');
});

test('recording spoil decreases stock balance through ledger', function (): void {
    Storage::fake('public');
    $user = operationUser('STAFF_BAR');
    seedDailyBalance($this, '5000');

    $this->actingAs($user)
        ->post(route('operations.spoil-wastes.store'), [
            'outlet_id' => $this->outlet->id,
            'department_id' => $this->department->id,
            'item_id' => $this->item->id,
            'qty_whole' => '2',
            'qty_loose' => '0',
            'recorded_date' => '2026-07-01',
            'reason_category' => SpoilWaste::REASON_TUMPAH,
            'reason_detail' => 'Tumpah saat prepare.',
            'photo' => UploadedFile::fake()->image('spoil.jpg', 640, 480),
        ])
        ->assertRedirect();

    $spoil = SpoilWaste::query()->firstOrFail();

    expect($spoil->status)->toBe(SpoilWaste::STATUS_PENDING)
        ->and((string) $spoil->qty_in_base_unit)->toBe('1000.000000')
        ->and($spoil->mutation_id)->not->toBeNull();

    $balance = StockBalance::query()
        ->where('item_id', $this->item->id)
        ->where('outlet_id', $this->outlet->id)
        ->where('stock_target', StockMutation::TARGET_OUTLET_DAILY)
        ->firstOrFail();

    expect((string) $balance->qty_on_hand)->toBe('4000.000000');
});

test('recording spoil with both whole and loose qty combines them into base unit correctly', function (): void {
    // Reproduksi keluhan user: form Catat Spoil sebelumnya cuma 1 field qty
    // generik, tidak bisa mencatat "2 utuh (dus/karton) + sisa 50 ecer
    // (gram/pcs)" sekaligus dalam 1 baris seperti Open Stock/Opname.
    Storage::fake('public');
    $user = operationUser('STAFF_BAR');
    seedDailyBalance($this, '5000');

    $this->actingAs($user)
        ->post(route('operations.spoil-wastes.store'), [
            'outlet_id' => $this->outlet->id,
            'department_id' => $this->department->id,
            'item_id' => $this->item->id,
            'qty_whole' => '2',
            'qty_loose' => '50',
            'recorded_date' => '2026-07-01',
            'reason_category' => SpoilWaste::REASON_TUMPAH,
        ])
        ->assertRedirect();

    $spoil = SpoilWaste::query()->firstOrFail();

    expect((string) $spoil->qty_whole)->toBe('2.000000')
        ->and((string) $spoil->qty_loose)->toBe('50.000000')
        // inventory_ratio item ini 500 -> (2 * 500) + 50 = 1050
        ->and((string) $spoil->qty_in_base_unit)->toBe('1050.000000');
});

test('recording spoil without any qty is rejected with a clear validation error', function (): void {
    Storage::fake('public');
    $user = operationUser('STAFF_BAR');
    seedDailyBalance($this, '5000');

    $this->actingAs($user)
        ->post(route('operations.spoil-wastes.store'), [
            'outlet_id' => $this->outlet->id,
            'department_id' => $this->department->id,
            'item_id' => $this->item->id,
            'qty_whole' => '0',
            'qty_loose' => '0',
            'recorded_date' => '2026-07-01',
            'reason_category' => SpoilWaste::REASON_TUMPAH,
        ])
        ->assertSessionHasErrors('qty_whole');

    expect(SpoilWaste::query()->count())->toBe(0);
});

test('duplicate spoil photo is detected by sha256 hash', function (): void {
    Storage::fake('public');
    $user = operationUser('STAFF_BAR');
    seedDailyBalance($this, '5000');
    $service = app(SpoilWasteService::class);

    $payload = [
        'tenant_id' => $this->tenant->id,
        'outlet_id' => $this->outlet->id,
        'department_id' => $this->department->id,
        'item_id' => $this->item->id,
        'unit_id' => $this->unit,
        'qty' => '1',
        'recorded_date' => '2026-07-01',
        'reason_category' => SpoilWaste::REASON_RUSAK,
        'reason_detail' => 'Kemasan rusak.',
    ];

    $first = $service->record(array_merge($payload, [
        'photo_file' => UploadedFile::fake()->createWithContent('proof.jpg', 'same-photo-content'),
    ]), $user->id);

    $second = $service->record(array_merge($payload, [
        'photo_file' => UploadedFile::fake()->createWithContent('proof.jpg', 'same-photo-content'),
    ]), $user->id);

    expect($first->is_duplicate_photo)->toBeFalse()
        ->and($second->is_duplicate_photo)->toBeTrue()
        ->and($second->duplicate_ref_id)->toBe($first->id);
});

test('rejecting spoil creates void reversal and restores balance', function (): void {
    // $this->unit = $this->item->inventory_unit_id, dan untuk item seeder
    // ini (MKO-AJINOMOTO-500GR) inventory_unit_id SAMA PERSIS dengan
    // base_unit_id (keduanya GR -- lihat MinimumMasterDataSeeder).
    // record() dipanggil di sini lewat jalur LAMA qty+unit_id (bukan
    // qty_whole/qty_loose), yang dikonversi lewat
    // SpoilWasteService::calculateBaseQty() -- method itu SENGAJA cek
    // base_unit_id LEBIH DULU (lihat komentar di calculateBaseQty()):
    // begitu $unitId === base_unit_id, faktor langsung 1, TIDAK PERNAH
    // sampai ke cabang inventory_ratio sama sekali, walaupun kolom
    // inventory_ratio item ini kebetulan terisi '500.000000'. Assertion
    // test ini SEBELUMNYA mengharapkan qty_change = '1000.000000' (qty=2
    // dikali ratio 500) -- itu ekspektasi usang dari SEBELUM fix ordering
    // base_unit_id-first ditambahkan ke calculateBaseQty(); dibuktikan
    // gagal identik di baseline d73ab98 (sebelum patch keamanan Spoil
    // Waste apa pun) lewat git worktree terisolasi, bukan cuma diasumsikan
    // dari membaca kode -- jadi ini BUKAN regresi dari patch keamanan, dan
    // assertion di sini diperbaiki ke semantik yang benar (faktor 1),
    // BUKAN calculateBaseQty() atau lifecycle ledger yang diubah. Lihat
    // test "recording and rejecting a spoil in a unit that genuinely
    // differs from the base unit applies inventory_ratio" di bawah untuk
    // pembanding jalur konversi rasio yang sungguhan dipakai.
    Storage::fake('public');
    $staff = operationUser('STAFF_BAR');
    $pic = operationUser('PIC_OUTLET');
    seedDailyBalance($this, '5000');

    expect((int) $this->item->inventory_unit_id)->toBe((int) $this->item->base_unit_id);

    $spoil = app(SpoilWasteService::class)->record([
        'tenant_id' => $this->tenant->id,
        'outlet_id' => $this->outlet->id,
        'department_id' => $this->department->id,
        'item_id' => $this->item->id,
        'unit_id' => $this->unit,
        'qty' => '2',
        'recorded_date' => '2026-07-01',
        'reason_category' => SpoilWaste::REASON_RUSAK,
    ], $staff->id);

    expect((string) $spoil->qty_in_base_unit)->toBe('2.000000');

    $this->actingAs($pic)
        ->post(route('operations.spoil-wastes.reject', $spoil), [
            'approval_notes' => 'Foto tidak valid.',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('stock_mutations', [
        'mutation_type' => StockMutation::TYPE_VOID_REVERSAL,
        'qty_change' => '2.000000',
    ]);

    $balance = StockBalance::query()
        ->where('item_id', $this->item->id)
        ->where('outlet_id', $this->outlet->id)
        ->where('stock_target', StockMutation::TARGET_OUTLET_DAILY)
        ->firstOrFail();

    expect($spoil->refresh()->status)->toBe(SpoilWaste::STATUS_REJECTED)
        ->and((string) $balance->qty_on_hand)->toBe('5000.000000');
});

test('recording and rejecting a spoil in a unit that genuinely differs from the base unit applies inventory_ratio', function (): void {
    // Pembanding langsung dari test di atas: di sini inventory_unit_id
    // SENGAJA dibuat BEDA dari base_unit_id (PACK vs GR, rasio 500) --
    // supaya cabang inventory_ratio di calculateBaseQty() benar-benar
    // teruji, bukan cuma item seeder standar yang kedua unitnya sama.
    // Item test ini LOKAL untuk test ini saja (replicate()), tidak
    // mengubah $this->item yang dipakai test lain.
    Storage::fake('public');
    $staff = operationUser('STAFF_BAR');
    $pic = operationUser('PIC_OUTLET');

    $packUnit = \App\Modules\Inventory\Models\Unit::query()
        ->where('tenant_id', $this->tenant->id)
        ->where('code', 'PACK')
        ->firstOrFail();

    $itemWithRatio = $this->item->replicate();
    $itemWithRatio->canonical_sku = 'TEST-RATIO-ITEM-'.uniqid();
    $itemWithRatio->inventory_unit_id = $packUnit->id;
    $itemWithRatio->inventory_ratio = '500.000000';
    $itemWithRatio->save();
    $itemWithRatio->update(['primary_department_id' => $this->department->id]);

    expect((int) $itemWithRatio->inventory_unit_id)->not->toBe((int) $itemWithRatio->base_unit_id);

    app(StockLedgerService::class)->openStock([
        'tenant_id' => $this->tenant->id,
        'outlet_id' => $this->outlet->id,
        'item_id' => $itemWithRatio->id,
        'unit_id' => $itemWithRatio->base_unit_id,
        'stock_target' => StockMutation::TARGET_OUTLET_DAILY,
        'qty' => '5000',
        'performed_by' => $staff->id,
    ]);

    $spoil = app(SpoilWasteService::class)->record([
        'tenant_id' => $this->tenant->id,
        'outlet_id' => $this->outlet->id,
        'department_id' => $this->department->id,
        'item_id' => $itemWithRatio->id,
        'unit_id' => $itemWithRatio->inventory_unit_id, // PACK, beda dari base_unit_id (GR).
        'qty' => '2', // 2 PACK x rasio 500 = 1000 GR (base unit).
        'recorded_date' => '2026-07-01',
        'reason_category' => SpoilWaste::REASON_RUSAK,
    ], $staff->id);

    expect((string) $spoil->qty_in_base_unit)->toBe('1000.000000');

    $balanceAfterSpoil = StockBalance::query()
        ->where('item_id', $itemWithRatio->id)
        ->where('outlet_id', $this->outlet->id)
        ->where('stock_target', StockMutation::TARGET_OUTLET_DAILY)
        ->firstOrFail();

    expect((string) $balanceAfterSpoil->qty_on_hand)->toBe('4000.000000');

    $this->actingAs($pic)
        ->post(route('operations.spoil-wastes.reject', $spoil), [
            'approval_notes' => 'Foto tidak valid.',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('stock_mutations', [
        'mutation_type' => StockMutation::TYPE_VOID_REVERSAL,
        'qty_change' => '1000.000000',
    ]);

    $balanceAfterReject = StockBalance::query()
        ->where('item_id', $itemWithRatio->id)
        ->where('outlet_id', $this->outlet->id)
        ->where('stock_target', StockMutation::TARGET_OUTLET_DAILY)
        ->firstOrFail();

    expect($spoil->refresh()->status)->toBe(SpoilWaste::STATUS_REJECTED)
        ->and((string) $balanceAfterReject->qty_on_hand)->toBe('5000.000000');
});

test('bulk approve only processes pending spoil waste rows', function (): void {
    Storage::fake('public');
    $staff = operationUser('STAFF_BAR');
    $pic = operationUser('PIC_OUTLET');
    seedDailyBalance($this, '5000');

    $spoil1 = app(SpoilWasteService::class)->record([
        'tenant_id' => $this->tenant->id,
        'outlet_id' => $this->outlet->id,
        'department_id' => $this->department->id,
        'item_id' => $this->item->id,
        'unit_id' => $this->unit,
        'qty' => '1',
        'recorded_date' => '2026-07-01',
        'reason_category' => SpoilWaste::REASON_RUSAK,
    ], $staff->id);

    $spoil2 = app(SpoilWasteService::class)->record([
        'tenant_id' => $this->tenant->id,
        'outlet_id' => $this->outlet->id,
        'department_id' => $this->department->id,
        'item_id' => $this->item->id,
        'unit_id' => $this->unit,
        'qty' => '1',
        'recorded_date' => '2026-07-01',
        'reason_category' => SpoilWaste::REASON_RUSAK,
    ], $staff->id);

    $this->actingAs($pic)
        ->post(route('operations.spoil-wastes.bulk-approve'), ['ids' => [$spoil1->id, $spoil2->id]])
        ->assertRedirect(route('operations.spoil-wastes.index'));

    expect($spoil1->refresh()->status)->toBe(SpoilWaste::STATUS_APPROVED)
        ->and($spoil2->refresh()->status)->toBe(SpoilWaste::STATUS_APPROVED);
});

test('daily opname creates session with items and posts adjustment on approval', function (): void {
    $staff = operationUser('STAFF_BAR');
    $pic = operationUser('PIC_OUTLET');
    seedDailyBalance($this, '5000');

    $session = app(OpnameService::class)->startSession([
        'tenant_id' => $this->tenant->id,
        'outlet_id' => $this->outlet->id,
        'type' => OpnameSession::TYPE_DAILY,
        'opname_date' => '2026-07-01',
        'shift' => 'PAGI',
    ], $staff->id);

    expect($session->items)->not->toBeEmpty();

    $opnameItem = $session->items->firstWhere('item_id', $this->item->id);
    app(OpnameService::class)->updateItem($opnameItem, '8', '0');

    foreach ($session->refresh()->items()->where('id', '!=', $opnameItem->id)->get() as $otherItem) {
        app(OpnameService::class)->updateItem($otherItem, '0', '0');
    }

    $this->actingAs($staff)
        ->post(route('operations.opname.submit', $session))
        ->assertRedirect();

    $this->actingAs($pic)
        ->post(route('operations.opname.approve', $session))
        ->assertRedirect();

    $this->assertDatabaseHas('stock_mutations', [
        'item_id' => $this->item->id,
        'mutation_type' => StockMutation::TYPE_DAILY_OPNAME_ADJ,
        'qty_change' => '-1000.000000',
    ]);

    $balance = StockBalance::query()
        ->where('item_id', $this->item->id)
        ->where('outlet_id', $this->outlet->id)
        ->where('stock_target', StockMutation::TARGET_OUTLET_DAILY)
        ->firstOrFail();

    expect($session->refresh()->status)->toBe(OpnameSession::STATUS_PROCESSED)
        ->and((string) $balance->qty_on_hand)->toBe('4000.000000');
});

test('opname baseline stays frozen after stock correction until manually synced', function (): void {
    $staff = operationUser('STAFF_BAR');
    seedDailyBalance($this, '5000');

    $session = app(OpnameService::class)->startSession([
        'tenant_id' => $this->tenant->id,
        'outlet_id' => $this->outlet->id,
        'type' => OpnameSession::TYPE_DAILY,
        'opname_date' => '2026-07-01',
        'shift' => 'PAGI',
    ], $staff->id);

    $opnameItem = $session->items->firstWhere('item_id', $this->item->id);
    expect((string) $opnameItem->system_qty_base)->toBe('5000.000000');

    // Koreksi stok TERJADI SETELAH sesi Opname sudah dibuat (mis. Open Stock
    // di-void lalu di-post ulang dengan angka benar) -- baseline yang sudah
    // dibekukan di baris Opname harus TETAP 5000 sampai disinkronkan manual.
    seedDailyBalance($this, '2000');

    expect((string) $opnameItem->refresh()->system_qty_base)->toBe('5000.000000');

    $synced = app(OpnameService::class)->syncItemBaseline($opnameItem);

    expect((string) $synced->system_qty_base)->toBe('7000.000000');
});

test('sync item endpoint updates baseline via HTTP and is blocked once session leaves draft', function (): void {
    $staff = operationUser('STAFF_BAR');
    $pic = operationUser('PIC_OUTLET');
    seedDailyBalance($this, '5000');

    $session = app(OpnameService::class)->startSession([
        'tenant_id' => $this->tenant->id,
        'outlet_id' => $this->outlet->id,
        'type' => OpnameSession::TYPE_DAILY,
        'opname_date' => '2026-07-02',
        'shift' => 'PAGI',
    ], $staff->id);

    $opnameItem = $session->items->firstWhere('item_id', $this->item->id);
    seedDailyBalance($this, '500');

    $response = $this->actingAs($staff)
        ->postJson(route('operations.opname.sync-item', [$session, $opnameItem]));

    expect($response->getStatusCode())->toBe(200)
        ->and($response->json('system_qty_base'))->toBe('5500.000000');

    expect((string) $opnameItem->refresh()->system_qty_base)->toBe('5500.000000');

    foreach ($session->refresh()->items as $item) {
        app(OpnameService::class)->updateItem($item, '0', '0');
    }
    $this->actingAs($staff)->post(route('operations.opname.submit', $session));

    expect($session->refresh()->status)->toBe(OpnameSession::STATUS_SUBMITTED);

    $beforeSync = (string) $opnameItem->refresh()->system_qty_base;

    $response = $this->actingAs($pic)
        ->postJson(route('operations.opname.sync-item', [$session, $opnameItem]));

    expect($response->exception)->toBeInstanceOf(ValidationException::class)
        ->and((string) $opnameItem->refresh()->system_qty_base)->toBe($beforeSync);
});
