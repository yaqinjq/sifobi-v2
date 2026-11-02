<?php

use App\Models\User;
use App\Modules\Core\Models\Outlet;
use App\Modules\Core\Models\Tenant;
use App\Modules\Inventory\Models\Item;
use App\Modules\Stock\Models\StockBalance;
use App\Modules\Stock\Models\StockMutation;
use App\Services\StockLedgerService;
use Database\Seeders\MinimumMasterDataSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * STAFF_* (restricted) adalah satu-satunya cabang di StockBalanceController
 * yang betul-betul dikunci ke outlet_id akun -- lanjutan akses multi-
 * outlet di sini SENGAJA jadi union/whereIn (konvensi permisif, bukan
 * fail-closed seperti Spoil & Waste), karena Stok Gudang sudah menampilkan
 * kolom outlet per baris (sama seperti Laporan Mutasi/Penerimaan).
 */
beforeEach(function (): void {
    $this->seed([
        RolesAndPermissionsSeeder::class,
        MinimumMasterDataSeeder::class,
    ]);

    $this->tenant = Tenant::query()->where('code', 'MKO')->firstOrFail();
    $this->outlet1 = Outlet::query()->where('tenant_id', $this->tenant->id)->where('code', 'MKO_OUTLET_1')->firstOrFail();
    $this->outlet2 = Outlet::query()->where('tenant_id', $this->tenant->id)->where('code', 'MKO_OUTLET_2')->firstOrFail();
    $this->item = Item::query()->where('tenant_id', $this->tenant->id)->where('canonical_sku', 'MKO-AJINOMOTO-500GR')->firstOrFail();

    foreach ([$this->outlet1, $this->outlet2] as $outlet) {
        app(StockLedgerService::class)->openStock([
            'tenant_id' => $this->tenant->id,
            'outlet_id' => $outlet->id,
            'item_id' => $this->item->id,
            'unit_id' => $this->item->base_unit_id,
            'stock_target' => StockMutation::TARGET_OUTLET_DAILY,
            'qty' => '50',
            'performed_by' => User::query()->where('email', 'admin@sifobi.test')->value('id'),
        ]);
    }
});

test('single-outlet staff still sees only their own outlet balances, 0 regression', function (): void {
    $user = User::factory()->create(['tenant_id' => $this->tenant->id, 'outlet_id' => $this->outlet1->id, 'status' => 'ACTIVE']);
    $user->assignRole('STAFF_BAR');

    $response = $this->actingAs($user)->get(route('stock.balance.index'));
    $response->assertOk();

    $outletIds = $response->viewData('balances')->pluck('outlet_id')->unique()->values()->all();
    expect($outletIds)->toBe([$this->outlet1->id]);
});

test('multi-outlet staff sees the union of balances from all their own outlets, not just one', function (): void {
    $user = User::factory()->create(['tenant_id' => $this->tenant->id, 'outlet_id' => $this->outlet1->id, 'status' => 'ACTIVE']);
    $user->assignRole('STAFF_BAR');
    $user->outlets()->sync([$this->outlet1->id, $this->outlet2->id]);

    $response = $this->actingAs($user)->get(route('stock.balance.index'));
    $response->assertOk();

    $outletIds = $response->viewData('balances')->pluck('outlet_id')->unique()->sort()->values()->all();
    expect($outletIds)->toBe(collect([$this->outlet1->id, $this->outlet2->id])->sort()->values()->all());
});

test('multi-outlet staff cannot widen scope to an outlet outside their own list via query string', function (): void {
    $thirdOutlet = Outlet::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenant->id,
        'brand_id' => $this->outlet1->brand_id,
        'legal_entity_id' => $this->outlet1->legal_entity_id,
        'code' => 'MKO_OUTLET_3_'.uniqid(),
        'name' => 'Outlet Ketiga',
        'status' => 'ACTIVE',
    ]);
    app(StockLedgerService::class)->openStock([
        'tenant_id' => $this->tenant->id,
        'outlet_id' => $thirdOutlet->id,
        'item_id' => $this->item->id,
        'unit_id' => $this->item->base_unit_id,
        'stock_target' => StockMutation::TARGET_OUTLET_DAILY,
        'qty' => '50',
        'performed_by' => User::query()->where('email', 'admin@sifobi.test')->value('id'),
    ]);

    $user = User::factory()->create(['tenant_id' => $this->tenant->id, 'outlet_id' => $this->outlet1->id, 'status' => 'ACTIVE']);
    $user->assignRole('STAFF_BAR');
    $user->outlets()->sync([$this->outlet1->id, $this->outlet2->id]);

    $response = $this->actingAs($user)->get(route('stock.balance.index', ['outlet_id' => $thirdOutlet->id]));
    $response->assertOk();

    $outletIds = $response->viewData('balances')->pluck('outlet_id')->unique()->all();
    expect($outletIds)->not->toContain($thirdOutlet->id);
});
