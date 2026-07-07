<?php

use App\Models\User;
use App\Modules\Core\Models\Outlet;
use App\Modules\Core\Models\Tenant;
use App\Modules\Inventory\Models\Item;
use App\Modules\Receiving\Models\GoodsReceipt;
use App\Modules\Receiving\Models\Supplier;
use App\Modules\Stock\Models\StockBalance;
use App\Modules\Stock\Models\StockMutation;
use Database\Seeders\MinimumMasterDataSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

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
    $this->item = Item::query()->where('canonical_sku', 'MKO-AJINOMOTO-500GR')->firstOrFail();
    /** @phpstan-ignore-next-line */
    $this->supplier = Supplier::query()->where('code', 'SUP-001')->firstOrFail();
});

function goodsReceiptUser(string $roleName): User
{
    $tenant = Tenant::query()->where('code', 'MKO')->firstOrFail();
    $outlet = Outlet::query()->where('code', 'MKO_OUTLET_1')->firstOrFail();

    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'outlet_id' => $outlet->id,
        'name' => $roleName.' User',
        'email' => strtolower($roleName).'_receiving@sifobi.test',
        'status' => 'ACTIVE',
    ]);

    $user->assignRole($roleName);

    return $user;
}

/**
 * @param  array<string, mixed>  $overrides
 * @param  array<string, mixed>  $itemOverrides
 * @return array<string, mixed>
 */
function goodsReceiptPayload(array $overrides = [], array $itemOverrides = []): array
{
    $outlet = Outlet::query()->where('code', 'MKO_OUTLET_1')->firstOrFail();
    $item = Item::query()->where('canonical_sku', 'MKO-AJINOMOTO-500GR')->firstOrFail();
    $supplier = Supplier::query()->where('code', 'SUP-001')->firstOrFail();

    return array_replace_recursive([
        'source' => GoodsReceipt::SOURCE_SUPPLIER_LUAR,
        'outlet_id' => $outlet->id,
        'supplier_id' => $supplier->id,
        'doc_number' => 'SJ-TEST-001',
        'invoice_number' => 'INV-TEST-001',
        'receipt_date' => '2026-07-01',
        'notes' => 'Penerimaan test',
        'items' => [
            array_merge([
                'item_id' => $item->id,
                'unit_id' => $item->purchase_unit_id,
                'qty_ordered' => '2',
                'qty_received' => '2',
                'unit_price' => '25000',
            ], $itemOverrides),
        ],
    ], $overrides);
}

test('guest cannot access goods receipt index', function (): void {
    $this->get(route('receiving.goods-receipts.index'))
        ->assertRedirect('/login');
});

test('staff bar cannot access goods receipt module', function (): void {
    $user = goodsReceiptUser('STAFF_BAR');

    $this->actingAs($user)
        ->get(route('receiving.goods-receipts.index'))
        ->assertForbidden();
});

test('create page shows four receiving sources', function (): void {
    $user = goodsReceiptUser('STAFF_GUDANG');

    $this->actingAs($user)
        ->get(route('receiving.goods-receipts.create'))
        ->assertOk()
        ->assertSee('Kopi dari OCIA')
        ->assertSee('WIP Central Kitchen')
        ->assertSee('Drygood Purchasing')
        ->assertSee('Supplier Luar');
});

test('staff gudang can create goods receipt draft', function (): void {
    $user = goodsReceiptUser('STAFF_GUDANG');

    $this->actingAs($user)
        ->post(route('receiving.goods-receipts.store'), goodsReceiptPayload())
        ->assertRedirect();

    $receipt = GoodsReceipt::query()->firstOrFail();

    expect($receipt->status)->toBe(GoodsReceipt::STATUS_DRAFT)
        ->and($receipt->items)->toHaveCount(1)
        ->and((string) $receipt->items->first()->qty_in_base_unit)->toBe('24000.000000');
});

test('pic outlet can submit draft for review', function (): void {
    $user = goodsReceiptUser('PIC_OUTLET');

    $this->actingAs($user)
        ->post(route('receiving.goods-receipts.store'), goodsReceiptPayload());

    $receipt = GoodsReceipt::query()->firstOrFail();

    $this->actingAs($user)
        ->post(route('receiving.goods-receipts.submit', $receipt))
        ->assertRedirect();

    expect($receipt->refresh()->status)->toBe(GoodsReceipt::STATUS_SUBMITTED)
        ->and($receipt->review_status)->toBe(GoodsReceipt::REVIEW_NEED_REVIEW);
});

test('approve posts goods receipt to stock ledger and balance', function (): void {
    $creator = goodsReceiptUser('PIC_OUTLET');
    $approver = goodsReceiptUser('MANAGER_AREA');

    $this->actingAs($creator)
        ->post(route('receiving.goods-receipts.store'), goodsReceiptPayload(['action' => 'submit']));

    $receipt = GoodsReceipt::query()->firstOrFail();

    $this->actingAs($approver)
        ->post(route('receiving.goods-receipts.approve', $receipt), [
            'review_notes' => 'Sesuai dokumen.',
        ])
        ->assertRedirect();

    $receipt->refresh();

    expect($receipt->status)->toBe(GoodsReceipt::STATUS_POSTED)
        ->and($receipt->items->first()->mutation_id)->not->toBeNull();

    $this->assertDatabaseHas('stock_mutations', [
        'tenant_id' => $this->tenant->id,
        'outlet_id' => $this->outlet->id,
        'item_id' => $this->item->id,
        'stock_target' => StockMutation::TARGET_OUTLET_WAREHOUSE,
        'mutation_type' => StockMutation::TYPE_PO_RECEIVE,
        'qty_change' => '24000.000000',
    ]);

    $balance = StockBalance::query()
        ->where('tenant_id', $this->tenant->id)
        ->where('outlet_id', $this->outlet->id)
        ->where('item_id', $this->item->id)
        ->where('stock_target', StockMutation::TARGET_OUTLET_WAREHOUSE)
        ->firstOrFail();

    expect((string) $balance->qty_on_hand)->toBe('24000.000000');
});

test('admin can reject submitted goods receipt', function (): void {
    $creator = goodsReceiptUser('PIC_OUTLET');
    $reviewer = goodsReceiptUser('ADMIN');

    $this->actingAs($creator)
        ->post(route('receiving.goods-receipts.store'), goodsReceiptPayload(['action' => 'submit']));

    $receipt = GoodsReceipt::query()->firstOrFail();

    $this->actingAs($reviewer)
        ->post(route('receiving.goods-receipts.reject', $receipt), [
            'review_notes' => 'Qty belum sesuai dokumen.',
        ])
        ->assertRedirect();

    expect($receipt->refresh()->status)->toBe(GoodsReceipt::STATUS_REJECTED)
        ->and($receipt->review_status)->toBe(GoodsReceipt::REVIEW_REJECTED);
});

test('super admin can add supplier from settings', function (): void {
    $user = User::query()->where('email', 'admin@sifobi.test')->firstOrFail();

    $this->actingAs($user)
        ->post(route('settings.suppliers.store'), [
            'code' => 'SUP-NEW',
            'name' => 'Supplier Baru',
            'phone' => '08123456789',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('suppliers', [
        'tenant_id' => $this->tenant->id,
        'code' => 'SUP-NEW',
        'name' => 'Supplier Baru',
    ]);
});
