<?php

use App\Modules\Core\Models\Department;
use App\Modules\Core\Models\Outlet;
use App\Modules\Core\Models\Tenant;
use App\Modules\Inventory\Models\Item;
use App\Modules\Procurement\Models\PurchaseOrder;
use App\Modules\Stock\Models\StockMutation;
use App\Services\OpnameService;
use App\Services\PurchaseOrderService;
use App\Services\StockLedgerService;
use App\Services\WiproIntegrationService;
use Database\Seeders\MinimumMasterDataSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Permintaan pimpinan & tim operasional: di halaman Opname, Spoil &
 * Waste, dan Purchase Order, selalu terlihat jelas siapa yang
 * menginput/mencatat datanya -- baik di daftar maupun di detail.
 */
beforeEach(function (): void {
    $this->seed([
        RolesAndPermissionsSeeder::class,
        MinimumMasterDataSeeder::class,
    ]);

    $this->tenant = Tenant::query()->where('code', 'MKO')->firstOrFail();
    $this->outlet = Outlet::query()->where('code', 'MKO_OUTLET_1')->firstOrFail();
    $this->bar = Department::query()->where('code', 'BAR')->firstOrFail();

    $this->barItem = Item::query()->where('canonical_sku', 'MKO-AJINOMOTO-500GR')->firstOrFail();
    $this->barItem->update(['primary_department_id' => $this->bar->id]);

    app(StockLedgerService::class)->openStock([
        'tenant_id' => $this->tenant->id,
        'outlet_id' => $this->outlet->id,
        'item_id' => $this->barItem->id,
        'unit_id' => $this->barItem->base_unit_id,
        'stock_target' => StockMutation::TARGET_OUTLET_DAILY,
        'qty' => '1000',
        'performed_by' => \App\Models\User::query()->where('email', 'admin@sifobi.test')->value('id'),
    ]);
});

test('opname index and show pages display who recorded the session', function (): void {
    $staff = opnameUser('STAFF_BAR', 'BAR');

    $session = app(OpnameService::class)->startSession([
        'tenant_id' => $this->tenant->id,
        'outlet_id' => $this->outlet->id,
        'department_id' => $this->bar->id,
        'type' => \App\Modules\Operations\Models\OpnameSession::TYPE_DAILY,
        'opname_date' => now()->toDateString(),
    ], $staff->id);

    $indexResponse = $this->actingAs($staff)->get(route('operations.opname.index'));
    $indexResponse->assertOk();
    $indexResponse->assertSee($staff->name);
    $indexResponse->assertSee('STAFF_BAR');

    $showResponse = $this->actingAs($staff)->get(route('operations.opname.show', $session));
    $showResponse->assertOk();
    $showResponse->assertSee('Dicatat oleh');
    $showResponse->assertSee($staff->name);
    $showResponse->assertSee('STAFF_BAR');
});

test('spoil waste index and show pages display who recorded it', function (): void {
    $staff = opnameUser('STAFF_BAR', 'BAR');

    $spoil = recordSpoilFor($staff, $this->outlet, $this->bar, $this->barItem);

    $indexResponse = $this->actingAs($staff)->get(route('operations.spoil-wastes.index'));
    $indexResponse->assertOk();
    $indexResponse->assertSee($staff->name);
    $indexResponse->assertSee('STAFF_BAR');

    $showResponse = $this->actingAs($staff)->get(route('operations.spoil-wastes.show', $spoil));
    $showResponse->assertOk();
    $showResponse->assertSee('Dicatat oleh');
    $showResponse->assertSee($staff->name);
    $showResponse->assertSee('STAFF_BAR');
});

test('purchase order index and show pages display who made the order, their role, and their outlet', function (): void {
    $staff = opnameUser('PIC_OUTLET', null);
    $staff->update(['outlet_id' => $this->outlet->id]);

    $po = app(PurchaseOrderService::class)->create([
        'tenant_id' => $this->tenant->id,
        'outlet_id' => $this->outlet->id,
        'po_type' => PurchaseOrder::TYPE_DRYGOOD,
    ], $staff->id);

    $indexResponse = $this->actingAs($staff)->get(route('procurement.purchase-orders.index'));
    $indexResponse->assertOk();
    $indexResponse->assertSee($staff->name);
    $indexResponse->assertSee('PIC_OUTLET');

    $showResponse = $this->actingAs($staff)->get(route('procurement.purchase-orders.show', $po));
    $showResponse->assertOk();
    $showResponse->assertSee('Dibuat oleh');
    $showResponse->assertSee($staff->name);
    $showResponse->assertSee('PIC_OUTLET');
    $showResponse->assertSee($this->outlet->name);
});

test('wipro order payload includes the requester role and outlet, not just the name', function (): void {
    $staff = opnameUser('PIC_OUTLET', null);
    $staff->update(['outlet_id' => $this->outlet->id]);

    $po = app(PurchaseOrderService::class)->create([
        'tenant_id' => $this->tenant->id,
        'outlet_id' => $this->outlet->id,
        'po_type' => PurchaseOrder::TYPE_DRYGOOD,
    ], $staff->id);

    $service = app(WiproIntegrationService::class);
    $method = new ReflectionMethod($service, 'payload');
    $method->setAccessible(true);
    $payload = $method->invoke($service, $po->fresh());

    expect($payload['requested_by_name'])->toBe($staff->name)
        ->and($payload['requested_by_role'])->toBe('PIC_OUTLET')
        ->and($payload['requested_by_outlet'])->toBe($this->outlet->name);
});
