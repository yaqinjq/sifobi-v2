<?php

use App\Models\User;
use App\Modules\Core\Models\Outlet;
use App\Modules\Core\Models\Tenant;
use App\Modules\Inventory\Models\Item;
use App\Modules\Inventory\Models\Unit;
use App\Modules\Procurement\Models\PurchaseOrder;
use App\Modules\Procurement\Models\PurchaseOrderApprovalEvent;
use App\Modules\Procurement\Models\PurchaseOrderShipment;
use App\Modules\Receiving\Models\Supplier;
use App\Services\PurchaseOrderService;
use Database\Seeders\MinimumMasterDataSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed([
        RolesAndPermissionsSeeder::class,
        MinimumMasterDataSeeder::class,
    ]);

    $this->tenant = Tenant::query()->where('code', 'MKO')->firstOrFail();
    $this->outlet = Outlet::query()->where('tenant_id', $this->tenant->id)->where('code', 'MKO_OUTLET_1')->firstOrFail();
    $this->item = Item::query()->where('tenant_id', $this->tenant->id)->where('canonical_sku', 'MKO-AJINOMOTO-500GR')->firstOrFail();
    $this->unit = Unit::query()->where('tenant_id', $this->tenant->id)->where('id', $this->item->base_unit_id)->firstOrFail();

    $this->supplier = Supplier::query()->create([
        'tenant_id' => $this->tenant->id,
        'code'      => 'SUP-TOKO-A',
        'name'      => 'Toko Sembako A',
        'is_active' => true,
    ]);
});

function drygoodTabsPayload(object $test): array
{
    return [
        'DRYGOOD' => [
            ['item_id' => $test->item->id, 'unit_id' => $test->unit->id, 'qty_ordered' => 10],
        ],
    ];
}

function picUser(object $test): User
{
    $user = User::factory()->create([
        'tenant_id' => $test->tenant->id,
        'outlet_id' => $test->outlet->id,
        'status'    => 'ACTIVE',
    ]);
    $user->assignRole('PIC_OUTLET');

    return $user;
}

/** Drive a fresh DRYGOOD PO all the way to SENT, via the real service calls. */
function sentDrygoodPo(object $test, User $user, ?int $supplierId = null): PurchaseOrder
{
    $service = app(PurchaseOrderService::class);

    [$po] = $service->createBatch([
        'tenant_id'  => $test->tenant->id,
        'outlet_id'  => $test->outlet->id,
        'needed_at'  => now()->addDay()->toDateString(),
        'tabs'       => drygoodTabsPayload($test),
        'supplier_id' => $supplierId,
    ], $user->id);

    $service->submit($po, $user->id);
    $service->approve($po, $user->id);
    $service->send($po, $user->id);

    return $po->refresh();
}

test('tab Drygood / Purchasing muncul di halaman create PO', function (): void {
    $user = picUser($this);

    $response = $this->actingAs($user)->get(route('procurement.purchase-orders.create'));

    $response->assertOk();
    expect($response->viewData('typeLabels'))->toHaveKey(PurchaseOrder::TYPE_DRYGOOD);
});

test('storeBatch tab Drygood dengan supplier_id tersimpan ke PO', function (): void {
    $user = picUser($this);

    $response = $this->actingAs($user)->postJson(route('procurement.purchase-orders.store-batch'), [
        'needed_at'   => now()->addDay()->toDateString(),
        'tabs'        => drygoodTabsPayload($this),
        'supplier_id' => $this->supplier->id,
    ]);

    $response->assertOk();

    $po = PurchaseOrder::query()->where('tenant_id', $this->tenant->id)->firstOrFail();
    expect($po->po_type)->toBe(PurchaseOrder::TYPE_DRYGOOD)
        ->and($po->supplier_id)->toBe($this->supplier->id)
        ->and($po->po_number)->toStartWith('PO-DG-');
});

test('storeBatch tab Drygood tanpa supplier_id -- kasus Purchasing Internal MKO', function (): void {
    $user = picUser($this);

    $response = $this->actingAs($user)->postJson(route('procurement.purchase-orders.store-batch'), [
        'needed_at' => now()->addDay()->toDateString(),
        'tabs'      => drygoodTabsPayload($this),
    ]);

    $response->assertOk();

    $po = PurchaseOrder::query()->where('tenant_id', $this->tenant->id)->firstOrFail();
    expect($po->supplier_id)->toBeNull();
});

test('supplier_id milik tenant lain ditolak validasi', function (): void {
    $otherTenant = Tenant::query()->create(['name' => 'Tenant Lain', 'code' => 'OTHER-'.uniqid()]);
    $foreignSupplier = Supplier::withoutGlobalScopes()->create([
        'tenant_id' => $otherTenant->id,
        'code'      => 'SUP-FOREIGN',
        'name'      => 'Supplier Tenant Lain',
        'is_active' => true,
    ]);

    $user = picUser($this);

    $response = $this->actingAs($user)->postJson(route('procurement.purchase-orders.store-batch'), [
        'needed_at'   => now()->addDay()->toDateString(),
        'tabs'        => drygoodTabsPayload($this),
        'supplier_id' => $foreignSupplier->id,
    ]);

    $response->assertStatus(422)->assertJsonValidationErrors('supplier_id');
});

test('send() untuk PO Drygood tidak memicu sync eksternal', function (): void {
    Queue::fake();

    $user = picUser($this);
    $po = sentDrygoodPo($this, $user);

    expect($po->status)->toBe(PurchaseOrder::STATUS_SENT)
        ->and($po->external_synced_at)->toBeNull()
        ->and($po->external_sync_error)->toBeNull();

    Queue::assertNotPushed(\App\Jobs\PushOrderToWiproJob::class);
});

test('recordShipment mencatat box pertama dan mengubah status jadi SHIPPED', function (): void {
    $user = picUser($this);
    $user->assignRole('PIC_OUTLET'); // sudah create_po + approve_po
    $po = sentDrygoodPo($this, $user);

    $response = $this->actingAs($user)->post(route('procurement.purchase-orders.shipments.store', $po), [
        'do_number'      => 'DO-001',
        'invoice_number' => 'INV-001',
        'notes'          => 'Box pertama',
    ]);

    $response->assertRedirect(route('procurement.purchase-orders.show', $po));

    $po->refresh();
    expect($po->status)->toBe(PurchaseOrder::STATUS_SHIPPED)
        ->and($po->shipments)->toHaveCount(1)
        ->and($po->shipments->first()->do_number)->toBe('DO-001')
        ->and(PurchaseOrderApprovalEvent::query()->where('purchase_order_id', $po->id)->where('event_type', PurchaseOrderApprovalEvent::EVENT_SHIP)->exists())->toBeTrue();
});

test('recordShipment box kedua tidak error dan status tetap SHIPPED', function (): void {
    $user = picUser($this);
    $po = sentDrygoodPo($this, $user);

    $this->actingAs($user)->post(route('procurement.purchase-orders.shipments.store', $po), [
        'do_number' => 'DO-001',
    ])->assertRedirect();

    $response = $this->actingAs($user)->post(route('procurement.purchase-orders.shipments.store', $po), [
        'do_number' => 'DO-002',
    ]);

    $response->assertRedirect(route('procurement.purchase-orders.show', $po));

    $po->refresh();
    expect($po->status)->toBe(PurchaseOrder::STATUS_SHIPPED)
        ->and($po->shipments)->toHaveCount(2);
});

test('recordShipment ditolak kalau PO belum dikirim (masih DRAFT/APPROVED)', function (): void {
    $user = picUser($this);
    $service = app(PurchaseOrderService::class);

    [$po] = $service->createBatch([
        'tenant_id' => $this->tenant->id,
        'outlet_id' => $this->outlet->id,
        'needed_at' => now()->addDay()->toDateString(),
        'tabs'      => drygoodTabsPayload($this),
    ], $user->id);

    $response = $this->actingAs($user)->post(route('procurement.purchase-orders.shipments.store', $po), [
        'do_number' => 'DO-001',
    ]);

    $response->assertRedirect();
    $response->assertSessionHasErrors('status');
    expect(PurchaseOrderShipment::query()->where('purchase_order_id', $po->id)->exists())->toBeFalse();
});

test('recordShipment ditolak (404) untuk PO tipe selain Drygood', function (): void {
    $user = picUser($this);
    $service = app(PurchaseOrderService::class);

    [$po] = $service->createBatch([
        'tenant_id' => $this->tenant->id,
        'outlet_id' => $this->outlet->id,
        'needed_at' => now()->addDay()->toDateString(),
        'tabs'      => [
            'OCIA_ROASTERY' => [
                ['item_id' => $this->item->id, 'unit_id' => $this->unit->id, 'qty_ordered' => 5],
            ],
        ],
    ], $user->id);
    $service->submit($po, $user->id);
    $service->approve($po, $user->id);

    $response = $this->actingAs($user)->post(route('procurement.purchase-orders.shipments.store', $po), [
        'do_number' => 'DO-001',
    ]);

    $response->assertNotFound();
});

test('recordShipment ditolak (403) untuk user tanpa permission approve_po', function (): void {
    $pic = picUser($this);
    $po = sentDrygoodPo($this, $pic);

    $staff = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'outlet_id' => $this->outlet->id,
        'status'    => 'ACTIVE',
    ]);
    $staff->assignRole('STAFF_BAR'); // create_po, bukan approve_po

    $response = $this->actingAs($staff)->post(route('procurement.purchase-orders.shipments.store', $po), [
        'do_number' => 'DO-001',
    ]);

    $response->assertForbidden();
});

test('tombol Kirim Ulang tidak pernah muncul untuk PO Drygood', function (): void {
    $user = picUser($this);
    $po = sentDrygoodPo($this, $user);

    $response = $this->actingAs($user)->get(route('procurement.purchase-orders.show', $po));

    $response->assertOk()->assertDontSee('Kirim Ulang');
});

test('setelah shipment dicatat, QR Box muncul dengan payload lengkap -- regresi utama fitur ini', function (): void {
    $user = picUser($this);
    $po = sentDrygoodPo($this, $user);

    $this->actingAs($user)->post(route('procurement.purchase-orders.shipments.store', $po), [
        'do_number'      => 'DO-QR-1',
        'invoice_number' => 'INV-QR-1',
    ])->assertRedirect();

    $response = $this->actingAs($user)->get(route('procurement.purchase-orders.show', $po));

    $response->assertOk()
        ->assertSee('Tampilkan QR Box')
        ->assertSee('&quot;po_id&quot;:'.$po->id, false)
        ->assertSee('&quot;do&quot;:&quot;DO-QR-1&quot;', false);
});
