<?php

use App\Models\User;
use App\Modules\Core\Models\Outlet;
use App\Modules\Core\Models\Tenant;
use App\Modules\Inventory\Models\Item;
use App\Modules\Operations\Models\SpoilWaste;
use App\Modules\Pos\Models\PosOrder;
use Database\Seeders\MinimumMasterDataSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed([
        RolesAndPermissionsSeeder::class,
        MinimumMasterDataSeeder::class,
    ]);

    $this->admin = User::query()->where('email', 'admin@sifobi.test')->firstOrFail();
    $this->tenant = Tenant::query()->where('code', 'MKO')->firstOrFail();
    $this->outlet = Outlet::query()->where('tenant_id', $this->tenant->id)->where('code', 'MKO_OUTLET_1')->firstOrFail();
    $this->item = Item::query()->where('canonical_sku', 'MKO-AJINOMOTO-500GR')->firstOrFail();
});

function dashboardSpoilRecord(object $test, string $recordedDate): SpoilWaste
{
    return SpoilWaste::query()->create([
        'tenant_id' => $test->tenant->id,
        'outlet_id' => $test->outlet->id,
        'item_id' => $test->item->id,
        'unit_id' => $test->item->base_unit_id,
        'qty' => '1',
        'qty_in_base_unit' => '1',
        'reason_category' => SpoilWaste::REASON_RUSAK,
        'recorded_date' => $recordedDate,
        'recorded_at' => $recordedDate,
        'status' => SpoilWaste::STATUS_PENDING,
        'approval_status' => SpoilWaste::STATUS_PENDING,
        'created_by' => $test->admin->id,
    ]);
}

test('dashboard shows spoil trend colored red when spoil increases vs yesterday', function (): void {
    dashboardSpoilRecord($this, now()->subDay()->toDateString());

    foreach (range(1, 3) as $i) {
        dashboardSpoilRecord($this, now()->toDateString());
    }

    $response = $this->actingAs($this->admin)->get('/dashboard');

    $response->assertOk();

    $trends = $response->viewData('trends');
    expect((int) $trends['spoil']->yesterday)->toBe(1);
    expect((int) $trends['spoil']->today)->toBe(3);
    expect($response->viewData('metrics')['spoil_today'])->toBe(3);

    $response->assertSee('text-red-500', false);
});

test('dashboard shows omzet metric only for users with view_pos_reports permission', function (): void {
    $this->actingAs($this->admin)
        ->get('/dashboard')
        ->assertSee('Omzet Hari Ini');

    $plainUser = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'outlet_id' => $this->outlet->id,
        'status' => 'ACTIVE',
    ]);

    $this->actingAs($plainUser)
        ->get('/dashboard')
        ->assertDontSee('Omzet Hari Ini');
});

test('dashboard omzet today sums only paid orders for accessible outlets', function (): void {
    PosOrder::query()->create([
        'tenant_id' => $this->tenant->id,
        'outlet_id' => $this->outlet->id,
        'order_number' => 'TEST-PAID-1',
        'order_type' => PosOrder::TYPE_DINE_IN,
        'status' => PosOrder::STATUS_PAID,
        'total_amount' => '150000',
        'closed_at' => now(),
        'created_by' => $this->admin->id,
    ]);

    PosOrder::query()->create([
        'tenant_id' => $this->tenant->id,
        'outlet_id' => $this->outlet->id,
        'order_number' => 'TEST-VOID-1',
        'order_type' => PosOrder::TYPE_DINE_IN,
        'status' => PosOrder::STATUS_VOID,
        'total_amount' => '999999',
        'closed_at' => now(),
        'created_by' => $this->admin->id,
    ]);

    $response = $this->actingAs($this->admin)->get('/dashboard');

    $response->assertOk();
    $response->assertSee('Rp 150.000');
    $response->assertDontSee('Rp 1.149.999');
});
