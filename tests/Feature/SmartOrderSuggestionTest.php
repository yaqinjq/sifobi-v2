<?php

use App\Models\User;
use App\Modules\Core\Models\Outlet;
use App\Modules\Core\Models\Tenant;
use App\Modules\Inventory\Models\Item;
use App\Modules\Operations\Models\OpnameSession;
use App\Modules\Stock\Models\StockMutation;
use App\Services\OpnameService;
use App\Services\SmartOrderService;
use Database\Seeders\MinimumMasterDataSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function smartOrderUser(string $role, int $tenantId, ?int $outletId): User
{
    $user = User::factory()->create([
        'tenant_id' => $tenantId,
        'outlet_id' => $outletId,
        'status' => 'ACTIVE',
    ]);
    $user->assignRole($role);

    return $user;
}

beforeEach(function (): void {
    $this->seed([
        RolesAndPermissionsSeeder::class,
        MinimumMasterDataSeeder::class,
    ]);

    $this->tenant = Tenant::query()->where('code', 'MKO')->firstOrFail();
    $this->outlet = Outlet::query()->where('code', 'MKO_OUTLET_1')->firstOrFail();
    $this->item = Item::query()->where('canonical_sku', 'MKO-AJINOMOTO-500GR')->firstOrFail();
});

test('smart order suggestion applies usage and event multiplier', function (): void {
    DB::table('item_stock_configs')->insert([
        'tenant_id' => $this->tenant->id,
        'item_id' => $this->item->id,
        'outlet_id' => $this->outlet->id,
        'min_stock_qty' => 10,
        'max_stock_qty' => 100,
        'reorder_point' => 20,
        'unit_id' => $this->item->base_unit_id,
        'avg_daily_usage_days' => 7,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('stock_balances')->insert([
        'tenant_id' => $this->tenant->id,
        'outlet_id' => $this->outlet->id,
        'item_id' => $this->item->id,
        'stock_target' => StockMutation::TARGET_OUTLET_DAILY,
        'qty_on_hand' => 10,
        'avg_cost' => 0,
        'total_value' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('stock_mutations')->insert([
        'tenant_id' => $this->tenant->id,
        'outlet_id' => $this->outlet->id,
        'item_id' => $this->item->id,
        'stock_target' => StockMutation::TARGET_OUTLET_DAILY,
        'unit_id' => $this->item->base_unit_id,
        'mutation_type' => StockMutation::TYPE_SPOIL_WASTE,
        'qty_change' => -14,
        'performed_at' => now()->subDay(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('calendar_events')->insert([
        'tenant_id' => $this->tenant->id,
        'outlet_id' => $this->outlet->id,
        'brand_id' => null,
        'name' => 'Promo Besar',
        'event_date' => today()->addDays(5),
        'event_type' => 'PROMO',
        'demand_multiplier' => 1.5,
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $suggestion = app(SmartOrderService::class)->getSuggestion(
        $this->item->id,
        $this->outlet->id,
        $this->tenant->id
    );

    expect($suggestion['current_qty'])->toBe(10.0)
        ->and($suggestion['avg_daily_usage'])->toBe(2.0)
        ->and($suggestion['days_remaining'])->toBe(5.0)
        ->and($suggestion['recommended_order'])->toBe(27.0)
        ->and($suggestion['is_below_reorder'])->toBeTrue()
        ->and($suggestion['is_critical'])->toBeTrue()
        ->and($suggestion['upcoming_events'])->toHaveCount(1);
});

test('general finance can access stock configuration settings', function (): void {
    $user = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'outlet_id' => null,
        'status' => 'ACTIVE',
    ]);
    $user->assignRole('GENERAL_FINANCE');

    $this->actingAs($user)
        ->get(route('settings.stock-configs.index'))
        ->assertOk()
        ->assertSee('Konfigurasi Stok');
});

test('manager area can access calendar events without broad settings permission', function (): void {
    $user = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'outlet_id' => $this->outlet->id,
        'status' => 'ACTIVE',
    ]);
    $user->assignRole('MANAGER_AREA');

    expect($user->can('manage_settings'))->toBeFalse()
        ->and($user->can('manage_calendar_events'))->toBeTrue();

    $this->actingAs($user)
        ->get(route('settings.calendar-events.index'))
        ->assertOk()
        ->assertSee('Kalender Event');
});

test('open stock suggestion is available but collapsed by default', function (): void {
    $user = smartOrderUser('STAFF_BAR', $this->tenant->id, $this->outlet->id);

    $this->actingAs($user)
        ->get(route('operations.open-stocks.create'))
        ->assertOk()
        ->assertSee('Lihat Saran Stok')
        ->assertSee('suggestionOpen: false', false);
});

test('opname item cards render prominent smart order suggestion container', function (): void {
    $user = smartOrderUser('STAFF_BAR', $this->tenant->id, $this->outlet->id);

    $session = app(OpnameService::class)->startSession([
        'tenant_id' => $this->tenant->id,
        'outlet_id' => $this->outlet->id,
        'type' => OpnameSession::TYPE_DAILY,
        'opname_date' => today()->toDateString(),
        'shift' => 'PAGI',
    ], $user->id);

    $this->actingAs($user)
        ->get(route('operations.opname.show', $session))
        ->assertOk()
        ->assertSee('Saran Order')
        ->assertSee('suggestionUrl', false);
});

test('stock balance detail renders analysis panel when config exists', function (): void {
    $user = smartOrderUser('PIC_OUTLET', $this->tenant->id, $this->outlet->id);

    DB::table('item_stock_configs')->insert([
        'tenant_id' => $this->tenant->id,
        'item_id' => $this->item->id,
        'outlet_id' => $this->outlet->id,
        'min_stock_qty' => 10,
        'max_stock_qty' => 100,
        'reorder_point' => 20,
        'unit_id' => $this->item->base_unit_id,
        'avg_daily_usage_days' => 7,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('stock_balances')->insert([
        'tenant_id' => $this->tenant->id,
        'outlet_id' => $this->outlet->id,
        'item_id' => $this->item->id,
        'stock_target' => StockMutation::TARGET_OUTLET_DAILY,
        'qty_on_hand' => 10,
        'avg_cost' => 0,
        'total_value' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('stock.balance.show', [
            'item' => $this->item,
            'outlet_id' => $this->outlet->id,
        ]))
        ->assertOk()
        ->assertSee('Analisis Stok &amp; Saran Order', false)
        ->assertSee('Rekomendasi Order');
});
