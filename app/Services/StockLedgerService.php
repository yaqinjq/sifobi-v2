<?php

namespace App\Services;

use App\Modules\Operations\Models\OpenStock;
use App\Modules\Operations\Models\OpnameItem;
use App\Modules\Operations\Models\SpoilWaste;
use App\Modules\Receiving\Models\GoodsReceipt;
use App\Modules\Stock\Models\StockBalance;
use App\Modules\Stock\Models\StockMutation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class StockLedgerService
{
    /**
     * @var list<string>
     */
    private const MUTATION_TYPES = [
        StockMutation::TYPE_OPEN_STOCK,
        StockMutation::TYPE_GOODS_RECEIVE,
        StockMutation::TYPE_PO_RECEIVE,
        StockMutation::TYPE_SPOIL_WASTE,
        StockMutation::TYPE_DAILY_OPNAME_ADJ,
        StockMutation::TYPE_MONTHLY_OPNAME_ADJ,
        StockMutation::TYPE_VOID_REVERSAL,
    ];

    /**
     * @var list<string>
     */
    private const STOCK_TARGETS = [
        StockMutation::TARGET_OUTLET_DAILY,
        StockMutation::TARGET_OUTLET_WAREHOUSE,
    ];

    /**
     * Create an immutable stock mutation and update the current balance.
     *
     * @param  array<string, mixed>  $payload
     */
    public function move(array $payload): StockMutation
    {
        $payload = $this->normalizePayload($payload);
        $this->validateMovePayload($payload);
        $this->assertTenantScope($payload);
        $this->assertAllowedQuantityDirection($payload);

        return DB::transaction(function () use ($payload): StockMutation {
            $balance = StockBalance::query()
                ->where('tenant_id', $payload['tenant_id'])
                ->where('outlet_id', $payload['outlet_id'])
                ->where('item_id', $payload['item_id'])
                ->where('stock_target', $payload['stock_target'])
                ->lockForUpdate()
                ->first();

            if (! $balance) {
                $balance = StockBalance::query()->create([
                    'tenant_id' => $payload['tenant_id'],
                    'outlet_id' => $payload['outlet_id'],
                    'item_id' => $payload['item_id'],
                    'stock_target' => $payload['stock_target'],
                    'qty_on_hand' => '0.000000',
                    'avg_cost' => '0.0000',
                    'total_value' => '0.0000',
                ]);
            }

            $newBalance = bcadd((string) $balance->qty_on_hand, $payload['qty_change'], 6);

            if (
                $payload['mutation_type'] !== StockMutation::TYPE_VOID_REVERSAL
                && bccomp($newBalance, '0.000000', 6) < 0
            ) {
                throw ValidationException::withMessages([
                    'qty_change' => 'Stock balance is not sufficient for this mutation.',
                ]);
            }

            $mutation = StockMutation::query()->create([
                'tenant_id' => $payload['tenant_id'],
                'outlet_id' => $payload['outlet_id'],
                'item_id' => $payload['item_id'],
                'stock_target' => $payload['stock_target'],
                'unit_id' => $payload['unit_id'],
                'source_mutation_id' => $payload['source_mutation_id'] ?? null,
                'mutation_type' => $payload['mutation_type'],
                'qty_change' => $payload['qty_change'],
                'balance_after' => $newBalance,
                'reference_type' => $payload['reference_type'] ?? null,
                'reference_id' => $payload['reference_id'] ?? null,
                'performed_by' => $payload['performed_by'] ?? null,
                'performed_at' => $payload['performed_at'],
                'notes' => $payload['notes'] ?? null,
                'metadata' => $payload['metadata'] ?? null,
            ]);

            $avgCost = $this->averageCostAfterMove($balance, $payload, $newBalance);

            $balance->forceFill([
                'qty_on_hand' => $newBalance,
                'avg_cost' => $avgCost,
                'total_value' => bcmul($newBalance, $avgCost, 4),
                'last_mutation_id' => $mutation->id,
                'last_mutation_at' => $payload['performed_at'],
            ])->save();

            return $mutation;
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function openStock(array $payload): StockMutation
    {
        return $this->move(array_merge($payload, [
            'mutation_type' => StockMutation::TYPE_OPEN_STOCK,
            'qty_change' => $payload['qty_change'] ?? $payload['qty'] ?? null,
            'reference_type' => $payload['reference_type'] ?? OpenStock::class,
        ]));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function receiveGoods(array $payload): StockMutation
    {
        return $this->move(array_merge($payload, [
            'mutation_type' => StockMutation::TYPE_GOODS_RECEIVE,
            'qty_change' => $payload['qty_change'] ?? $payload['qty_received'] ?? $payload['qty'] ?? null,
            'reference_type' => $payload['reference_type'] ?? GoodsReceipt::class,
        ]));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function receivePurchaseOrder(array $payload): StockMutation
    {
        return $this->move(array_merge($payload, [
            'mutation_type' => StockMutation::TYPE_PO_RECEIVE,
            'qty_change' => $payload['qty_change'] ?? $payload['qty_received'] ?? $payload['qty'] ?? null,
            'reference_type' => $payload['reference_type'] ?? GoodsReceipt::class,
        ]));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function spoilWaste(array $payload): StockMutation
    {
        $qty = $payload['qty'] ?? $payload['qty_change'] ?? null;

        return $this->move(array_merge($payload, [
            'mutation_type' => StockMutation::TYPE_SPOIL_WASTE,
            'qty_change' => $qty === null ? null : $this->asNegativeQuantity($qty),
            'reference_type' => $payload['reference_type'] ?? SpoilWaste::class,
        ]));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function opnameAdjustment(array $payload): StockMutation
    {
        $mutationType = ($payload['opname_type'] ?? 'DAILY') === 'MONTHLY'
            ? StockMutation::TYPE_MONTHLY_OPNAME_ADJ
            : StockMutation::TYPE_DAILY_OPNAME_ADJ;

        return $this->move(array_merge($payload, [
            'mutation_type' => $payload['mutation_type'] ?? $mutationType,
            'qty_change' => $payload['qty_change'] ?? $payload['variance_qty'] ?? null,
            'reference_type' => $payload['reference_type'] ?? OpnameItem::class,
        ]));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function voidMutation(int|StockMutation $mutation, array $payload = []): StockMutation
    {
        return DB::transaction(function () use ($mutation, $payload): StockMutation {
            $original = $mutation instanceof StockMutation
                ? StockMutation::query()->lockForUpdate()->findOrFail($mutation->getKey())
                : StockMutation::query()->lockForUpdate()->findOrFail($mutation);

            if ($original->mutation_type === StockMutation::TYPE_VOID_REVERSAL) {
                throw ValidationException::withMessages([
                    'mutation_id' => 'VOID_REVERSAL mutations cannot be voided.',
                ]);
            }

            $alreadyVoided = StockMutation::query()
                ->where('source_mutation_id', $original->id)
                ->where('mutation_type', StockMutation::TYPE_VOID_REVERSAL)
                ->exists();

            if ($alreadyVoided) {
                throw ValidationException::withMessages([
                    'mutation_id' => 'This stock mutation already has a VOID_REVERSAL.',
                ]);
            }

            return $this->move([
                'tenant_id' => $original->tenant_id,
                'outlet_id' => $original->outlet_id,
                'item_id' => $original->item_id,
                'unit_id' => $original->unit_id,
                'stock_target' => $original->stock_target,
                'source_mutation_id' => $original->id,
                'mutation_type' => StockMutation::TYPE_VOID_REVERSAL,
                'qty_change' => $this->multiplyByNegativeOne($original->qty_change),
                'reference_type' => $payload['reference_type'] ?? StockMutation::class,
                'reference_id' => $payload['reference_id'] ?? $original->id,
                'performed_by' => $payload['performed_by'] ?? null,
                'performed_at' => $payload['performed_at'] ?? Carbon::now(),
                'notes' => $payload['notes'] ?? null,
                'metadata' => array_filter([
                    'void_reason' => $payload['void_reason'] ?? null,
                    'original_reference_type' => $original->reference_type,
                    'original_reference_id' => $original->reference_id,
                ], fn (mixed $value): bool => $value !== null),
            ]);
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function normalizePayload(array $payload): array
    {
        if (array_key_exists('qty_change', $payload) && $payload['qty_change'] !== null) {
            $payload['qty_change'] = $this->decimal($payload['qty_change']);
        }

        $payload['performed_at'] = isset($payload['performed_at'])
            ? Carbon::parse($payload['performed_at'])
            : Carbon::now();

        $payload['stock_target'] = $payload['stock_target'] ?? StockMutation::TARGET_OUTLET_DAILY;

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function validateMovePayload(array $payload): void
    {
        Validator::make($payload, [
            'tenant_id' => ['required', 'integer', 'exists:tenants,id'],
            'outlet_id' => ['required', 'integer', 'exists:outlets,id'],
            'item_id' => ['required', 'integer', 'exists:items,id'],
            'unit_id' => ['required', 'integer', 'exists:units,id'],
            'stock_target' => ['required', 'string', Rule::in(self::STOCK_TARGETS)],
            'source_mutation_id' => ['nullable', 'integer', 'exists:stock_mutations,id'],
            'mutation_type' => ['required', 'string', Rule::in(self::MUTATION_TYPES)],
            'qty_change' => ['required', 'numeric'],
            'reference_type' => ['nullable', 'string', 'max:100'],
            'reference_id' => ['nullable', 'integer'],
            'performed_by' => ['nullable', 'integer', 'exists:users,id'],
            'performed_at' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
            'metadata' => ['nullable', 'array'],
        ])->validate();

        if (
            $payload['mutation_type'] !== StockMutation::TYPE_OPEN_STOCK
            && bccomp($payload['qty_change'], '0.000000', 6) === 0
        ) {
            throw ValidationException::withMessages([
                'qty_change' => 'Quantity change must not be zero for this mutation type.',
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function assertTenantScope(array $payload): void
    {
        $checks = [
            'outlet_id' => DB::table('outlets')->where('id', $payload['outlet_id'])->value('tenant_id'),
            'item_id' => DB::table('items')->where('id', $payload['item_id'])->value('tenant_id'),
            'unit_id' => DB::table('units')->where('id', $payload['unit_id'])->value('tenant_id'),
        ];

        foreach ($checks as $field => $tenantId) {
            if ((int) $tenantId !== (int) $payload['tenant_id']) {
                throw ValidationException::withMessages([
                    $field => 'The selected value does not belong to the given tenant.',
                ]);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function assertAllowedQuantityDirection(array $payload): void
    {
        $comparison = bccomp($payload['qty_change'], '0.000000', 6);

        if (
            in_array($payload['mutation_type'], [StockMutation::TYPE_GOODS_RECEIVE, StockMutation::TYPE_PO_RECEIVE], true)
            && $comparison <= 0
        ) {
            throw ValidationException::withMessages([
                'qty_change' => "{$payload['mutation_type']} must increase stock.",
            ]);
        }

        if ($payload['mutation_type'] === StockMutation::TYPE_SPOIL_WASTE && $comparison >= 0) {
            throw ValidationException::withMessages([
                'qty_change' => 'SPOIL_WASTE must decrease stock.',
            ]);
        }
    }

    private function multiplyByNegativeOne(mixed $value): string
    {
        return bcmul($this->decimal($value), '-1', 6);
    }

    private function asNegativeQuantity(mixed $value): string
    {
        $decimal = $this->decimal($value);

        if (bccomp($decimal, '0.000000', 6) > 0) {
            return bcmul($decimal, '-1', 6);
        }

        return $decimal;
    }

    private function decimal(mixed $value): string
    {
        if (is_float($value) || is_int($value)) {
            return number_format($value, 6, '.', '');
        }

        $value = trim((string) $value);

        if (! preg_match('/^[+-]?\d+(\.\d+)?$/', $value)) {
            throw ValidationException::withMessages([
                'qty_change' => 'Quantity must be a valid decimal number.',
            ]);
        }

        $isNegative = str_starts_with($value, '-');
        $unsigned = ltrim($value, '+-');
        [$whole, $fraction] = array_pad(explode('.', $unsigned, 2), 2, '');
        $whole = ltrim($whole, '0') ?: '0';
        $fraction = str_pad(substr($fraction, 0, 6), 6, '0');

        if ($whole === '0' && $fraction === '000000') {
            $isNegative = false;
        }

        return ($isNegative ? '-' : '').$whole.'.'.$fraction;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function averageCostAfterMove(StockBalance $balance, array $payload, string $newBalance): string
    {
        $currentAvgCost = $this->moneyDecimal($balance->avg_cost ?? 0);
        $currentTotalValue = $this->moneyDecimal(
            $balance->total_value ?? bcmul((string) $balance->qty_on_hand, $currentAvgCost, 4)
        );

        $incomingValue = $this->incomingStockValue($payload);

        if ($incomingValue === null || bccomp($newBalance, '0.000000', 6) <= 0) {
            return $currentAvgCost;
        }

        return bcdiv(bcadd($currentTotalValue, $incomingValue, 4), $newBalance, 4);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function incomingStockValue(array $payload): ?string
    {
        if (bccomp($payload['qty_change'], '0.000000', 6) <= 0) {
            return null;
        }

        if (! in_array($payload['mutation_type'], [
            StockMutation::TYPE_OPEN_STOCK,
            StockMutation::TYPE_GOODS_RECEIVE,
            StockMutation::TYPE_PO_RECEIVE,
        ], true)) {
            return null;
        }

        $metadata = is_array($payload['metadata'] ?? null) ? $payload['metadata'] : [];

        foreach (['total_value', 'line_total_value'] as $key) {
            if (array_key_exists($key, $metadata) && $metadata[$key] !== null && $metadata[$key] !== '') {
                return $this->moneyDecimal($metadata[$key]);
            }
        }

        foreach (['unit_cost_base', 'cost_per_unit', 'avg_cost'] as $key) {
            if (array_key_exists($key, $metadata) && $metadata[$key] !== null && $metadata[$key] !== '') {
                return bcmul($payload['qty_change'], $this->moneyDecimal($metadata[$key]), 4);
            }
        }

        return null;
    }

    private function moneyDecimal(mixed $value): string
    {
        if (is_float($value) || is_int($value)) {
            return number_format($value, 4, '.', '');
        }

        $value = trim((string) $value);

        if ($value === '') {
            return '0.0000';
        }

        if (! preg_match('/^[+-]?\d+(\.\d+)?$/', $value)) {
            return '0.0000';
        }

        return number_format((float) $value, 4, '.', '');
    }
}
