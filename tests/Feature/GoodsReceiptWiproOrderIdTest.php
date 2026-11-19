<?php

use App\Models\User;
use App\Modules\Core\Models\Outlet;
use App\Modules\Core\Models\Tenant;
use App\Modules\Procurement\Models\PurchaseOrder;
use Database\Seeders\MinimumMasterDataSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Regresi utk bug 2026-11-19: Wipro mulai mencetak QR di dokumen "Detail
 * Order"/surat jalan mereka sendiri, isinya URL
 * "https://wipro.mykopiogroup.com/orders/{wipro_order_id}" (dibuktikan
 * dengan decode QR langsung dari screenshot user) -- bukan QR SIFOBI dan
 * bukan barcode item, jadi scan-nya selalu gagal "barcode tidak ditemukan".
 * wipro_order_id (ID numerik mentah dari Wipro) sebelumnya tidak pernah
 * disimpan sama sekali (cuma dipakai sbg fallback terakhir ke
 * external_reference), jadi tidak ada cara mencocokkan QR itu balik ke PO.
 */
uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed([
        RolesAndPermissionsSeeder::class,
        MinimumMasterDataSeeder::class,
    ]);

    $this->tenant = Tenant::query()->where('code', 'MKO')->firstOrFail();
    $this->outlet = Outlet::query()->where('tenant_id', $this->tenant->id)->where('code', 'MKO_OUTLET_1')->firstOrFail();
});

function goodsReceiptStaffUser(object $test): User
{
    $user = User::factory()->create([
        'tenant_id' => $test->tenant->id,
        'outlet_id' => $test->outlet->id,
        'status' => 'ACTIVE',
    ]);
    $user->assignRole('STAFF_GUDANG');

    return $user;
}

test('shippedPos di form penerimaan barang menyertakan wipro_order_id', function (): void {
    $po = PurchaseOrder::query()->create([
        'tenant_id' => $this->tenant->id,
        'outlet_id' => $this->outlet->id,
        'po_number' => 'PO-CK-2611-0124',
        'po_type' => PurchaseOrder::TYPE_CENTRAL_KITCHEN,
        'status' => PurchaseOrder::STATUS_SHIPPED,
        'external_reference' => 'WIP-20261112-0004',
        'wipro_order_id' => '759',
    ]);

    $user = goodsReceiptStaffUser($this);

    $response = $this->actingAs($user)->get(route('receiving.goods-receipts.create'));

    $response->assertOk();

    $shippedPo = collect($response->viewData('shippedPos'))->firstWhere('id', $po->id);

    expect($shippedPo)->not->toBeNull();
    expect($shippedPo['wipro_order_id'])->toBe('759');
});

test('PO belum pernah di-push ke Wipro (wipro_order_id null) tidak ikut cocok dengan QR apapun', function (): void {
    $po = PurchaseOrder::query()->create([
        'tenant_id' => $this->tenant->id,
        'outlet_id' => $this->outlet->id,
        'po_number' => 'PO-CK-2611-0099',
        'po_type' => PurchaseOrder::TYPE_CENTRAL_KITCHEN,
        'status' => PurchaseOrder::STATUS_SENT,
    ]);

    $user = goodsReceiptStaffUser($this);

    $response = $this->actingAs($user)->get(route('receiving.goods-receipts.create'));

    $response->assertOk();

    $shippedPo = collect($response->viewData('shippedPos'))->firstWhere('id', $po->id);

    expect($shippedPo)->not->toBeNull();
    expect($shippedPo['wipro_order_id'])->toBeNull();
});
