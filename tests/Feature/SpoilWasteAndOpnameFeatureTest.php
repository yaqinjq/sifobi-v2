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
            'unit_id' => $this->unit,
            'qty' => '2',
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
    Storage::fake('public');
    $staff = operationUser('STAFF_BAR');
    $pic = operationUser('PIC_OUTLET');
    seedDailyBalance($this, '5000');

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

    $this->actingAs($pic)
        ->post(route('operations.spoil-wastes.reject', $spoil), [
            'approval_notes' => 'Foto tidak valid.',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('stock_mutations', [
        'mutation_type' => StockMutation::TYPE_VOID_REVERSAL,
        'qty_change' => '1000.000000',
    ]);

    $balance = StockBalance::query()
        ->where('item_id', $this->item->id)
        ->where('outlet_id', $this->outlet->id)
        ->where('stock_target', StockMutation::TARGET_OUTLET_DAILY)
        ->firstOrFail();

    expect($spoil->refresh()->status)->toBe(SpoilWaste::STATUS_REJECTED)
        ->and((string) $balance->qty_on_hand)->toBe('5000.000000');
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
