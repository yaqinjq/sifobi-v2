<?php

use App\Models\User;
use App\Modules\Core\Models\Department;
use App\Modules\Core\Models\Outlet;
use App\Modules\Core\Models\Tenant;
use App\Modules\Inventory\Models\Item;
use App\Services\GoodsReceiptService;
use App\Services\SpoilWasteService;
use App\Services\StockLedgerService;
use Database\Seeders\MinimumMasterDataSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed([
        RolesAndPermissionsSeeder::class,
        MinimumMasterDataSeeder::class,
    ]);

    $this->tenant = Tenant::query()->where('code', 'MKO')->firstOrFail();
    $this->outlet = Outlet::query()->where('tenant_id', $this->tenant->id)->where('code', 'MKO_OUTLET_1')->firstOrFail();
    $this->kitchen = Department::query()->where('code', 'KITCHEN')->firstOrFail();
    $this->item = Item::query()->where('tenant_id', $this->tenant->id)->where('canonical_sku', 'MKO-AJINOMOTO-500GR')->firstOrFail();

    $this->admin = User::query()->where('email', 'admin@sifobi.test')->firstOrFail();
});

test('penerimaan barang diposting ke ledger pakai receipt_date yang diinput, bukan tanggal hari ini', function (): void {
    $pastDate = now()->subMonth()->startOfMonth()->addDays(2);

    $service = app(GoodsReceiptService::class);

    $draft = $service->createDraft([
        'tenant_id'    => $this->tenant->id,
        'outlet_id'    => $this->outlet->id,
        'source'       => \App\Modules\Receiving\Models\GoodsReceipt::SOURCE_PURCHASING_DRYGOOD,
        'receipt_date' => $pastDate->toDateString(),
        'items'        => [[
            'item_id'      => $this->item->id,
            'unit_id'      => $this->item->base_unit_id,
            'qty_received' => 20,
            'unit_price'   => 1000,
        ]],
    ], (int) $this->admin->id);

    $submitted = $service->submit($draft, (int) $this->admin->id);
    $created = $service->approve($submitted, (int) $this->admin->id);

    $mutation = \App\Modules\Stock\Models\StockMutation::query()
        ->where('reference_type', \App\Modules\Receiving\Models\GoodsReceipt::class)
        ->where('reference_id', $created->id)
        ->firstOrFail();

    expect($mutation->performed_at->toDateString())->toBe($pastDate->toDateString());
});

test('spoil waste tercatat dan diposting ke ledger pakai recorded_date yang diinput, bukan tanggal hari ini', function (): void {
    $pastDate = now()->subMonth()->startOfMonth()->addDays(2);

    app(StockLedgerService::class)->receiveGoods([
        'tenant_id'    => $this->tenant->id,
        'outlet_id'    => $this->outlet->id,
        'item_id'      => $this->item->id,
        'unit_id'      => $this->item->base_unit_id,
        'qty'          => 50,
        'performed_at' => $pastDate->copy()->subDay(),
    ]);

    $spoilService = app(SpoilWasteService::class);

    $spoil = $spoilService->record([
        'tenant_id'      => $this->tenant->id,
        'outlet_id'      => $this->outlet->id,
        'department_id'  => $this->kitchen->id,
        'recorded_date'  => $pastDate->toDateString(),
        'item_id'        => $this->item->id,
        'unit_id'        => $this->item->base_unit_id,
        'qty'            => 5,
        'reason_category' => 'RUSAK',
    ], (int) $this->admin->id);

    expect($spoil->recorded_at->toDateString())->toBe($pastDate->toDateString());

    $approved = $spoilService->approve($spoil, (int) $this->admin->id, 'ok');

    $mutation = \App\Modules\Stock\Models\StockMutation::query()
        ->where('reference_type', \App\Modules\Operations\Models\SpoilWaste::class)
        ->where('reference_id', $approved->id)
        ->firstOrFail();

    expect($mutation->performed_at->toDateString())->toBe($pastDate->toDateString());
});
