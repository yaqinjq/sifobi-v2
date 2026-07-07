<?php

use App\Models\User;
use App\Modules\Core\Models\Outlet;
use App\Modules\Core\Models\Tenant;
use App\Modules\Inventory\Models\Item;
use App\Modules\Inventory\Models\Unit;
use App\Modules\Stock\Models\StockBalance;
use App\Modules\Stock\Models\StockMutation;
use App\Services\StockLedgerService;
use Database\Seeders\MinimumMasterDataSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed([
        RolesAndPermissionsSeeder::class,
        MinimumMasterDataSeeder::class,
    ]);

    $this->tenant = Tenant::query()->where('code', 'MKO')->firstOrFail();
    $this->outlet = Outlet::query()->where('code', 'MKO_OUTLET_1')->firstOrFail();
    $this->item = Item::query()->where('canonical_sku', 'MKO-AJINOMOTO-500GR')->firstOrFail();
    $this->unit = Unit::query()->where('code', 'GR')->firstOrFail();
    $this->user = User::query()->where('email', 'admin@sifobi.test')->firstOrFail();
    $this->service = app(StockLedgerService::class);
});

function ledgerPayload(object $test, string $qty): array
{
    return [
        'tenant_id' => $test->tenant->id,
        'outlet_id' => $test->outlet->id,
        'item_id' => $test->item->id,
        'unit_id' => $test->unit->id,
        'qty' => $qty,
        'performed_by' => $test->user->id,
    ];
}

test('OPEN_STOCK creates stock mutation and increases stock balance', function (): void {
    $mutation = $this->service->openStock(ledgerPayload($this, '10.500'));

    expect($mutation->mutation_type)->toBe(StockMutation::TYPE_OPEN_STOCK)
        ->and((string) $mutation->qty_change)->toBe('10.500000')
        ->and((string) $mutation->balance_after)->toBe('10.500000');

    $balance = StockBalance::query()
        ->where('tenant_id', $this->tenant->id)
        ->where('outlet_id', $this->outlet->id)
        ->where('item_id', $this->item->id)
        ->firstOrFail();

    expect((string) $balance->qty_on_hand)->toBe('10.500000')
        ->and($balance->last_mutation_id)->toBe($mutation->id);
});

test('GOODS_RECEIVE increases stock balance', function (): void {
    $this->service->openStock(ledgerPayload($this, '10'));

    $mutation = $this->service->receiveGoods(ledgerPayload($this, '2.250'));

    expect($mutation->mutation_type)->toBe(StockMutation::TYPE_GOODS_RECEIVE)
        ->and((string) $mutation->qty_change)->toBe('2.250000')
        ->and((string) $mutation->balance_after)->toBe('12.250000');
});

test('PO_RECEIVE increases stock balance', function (): void {
    $this->service->openStock(ledgerPayload($this, '10'));

    $mutation = $this->service->receivePurchaseOrder(ledgerPayload($this, '4.500'));

    expect($mutation->mutation_type)->toBe(StockMutation::TYPE_PO_RECEIVE)
        ->and((string) $mutation->qty_change)->toBe('4.500000')
        ->and((string) $mutation->balance_after)->toBe('14.500000');
});

test('SPOIL_WASTE decreases stock balance', function (): void {
    $this->service->openStock(ledgerPayload($this, '10'));

    $mutation = $this->service->spoilWaste(ledgerPayload($this, '3.125'));

    expect($mutation->mutation_type)->toBe(StockMutation::TYPE_SPOIL_WASTE)
        ->and((string) $mutation->qty_change)->toBe('-3.125000')
        ->and((string) $mutation->balance_after)->toBe('6.875000');
});

test('SPOIL_WASTE fails when stock is not sufficient', function (): void {
    $this->service->openStock(ledgerPayload($this, '2'));

    expect(fn () => $this->service->spoilWaste(ledgerPayload($this, '3')))
        ->toThrow(ValidationException::class);

    expect(StockMutation::query()->where('mutation_type', StockMutation::TYPE_SPOIL_WASTE)->count())->toBe(0);
});

test('VOID_REVERSAL creates a new mutation with opposite quantity', function (): void {
    $original = $this->service->openStock(ledgerPayload($this, '10'));

    $void = $this->service->voidMutation($original, [
        'performed_by' => $this->user->id,
        'void_reason' => 'Input correction',
    ]);

    expect($void->id)->not->toBe($original->id)
        ->and($void->mutation_type)->toBe(StockMutation::TYPE_VOID_REVERSAL)
        ->and($void->source_mutation_id)->toBe($original->id)
        ->and((string) $void->qty_change)->toBe('-10.000000')
        ->and((string) $void->balance_after)->toBe('0.000000');
});

test('VOID_REVERSAL does not mutate the original mutation', function (): void {
    $original = $this->service->openStock(ledgerPayload($this, '10'));
    $snapshot = $original->only(['id', 'mutation_type', 'qty_change', 'balance_after']);

    $this->service->voidMutation($original, ['performed_by' => $this->user->id]);

    $original->refresh();

    expect($original->only(['id', 'mutation_type', 'qty_change', 'balance_after']))->toBe($snapshot)
        ->and(StockMutation::query()->count())->toBe(2);
});

test('stock mutations cannot be updated or deleted normally', function (): void {
    $mutation = $this->service->openStock(ledgerPayload($this, '10'));

    expect(fn () => $mutation->update(['notes' => 'Edited']))
        ->toThrow(LogicException::class);

    expect(fn () => $mutation->delete())
        ->toThrow(LogicException::class);

    expect(StockMutation::query()->count())->toBe(1);
});
