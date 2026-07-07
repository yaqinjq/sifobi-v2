<?php

namespace App\Http\Requests\MasterData;

use App\Support\Decimal;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreItemRequest extends FormRequest
{
    public const ITEM_TYPES = [
        'BAHAN_BAKU',
        'WIP_L1',
        'WIP_L2',
        'WIP_L3',
        'PACKAGING',
        'MENU_ITEM',
    ];

    public const ITEM_TYPE_OPTIONS = [
        'BAHAN_BAKU' => 'Bahan Baku Mentah',
        'WIP_L1' => 'WIP Level 1 (Premix/Sirup)',
        'WIP_L2' => 'WIP Level 2 (Assembly)',
        'WIP_L3' => 'WIP Level 3 (Final Prep)',
        'PACKAGING' => 'Packaging/Kemasan',
        'MENU_ITEM' => 'Menu Item (Produk Jadi)',
    ];

    public function authorize(): bool
    {
        return $this->user()?->can('manage_items') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->boolean('is_active'),
            'track_expiry' => $this->boolean('track_expiry'),
            'canonical_sku' => strtoupper(trim((string) $this->input('canonical_sku'))),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $tenantId = (int) $this->user()->tenant_id;

        return [
            'canonical_sku' => [
                'required',
                'string',
                'max:50',
                Rule::unique('items', 'canonical_sku')->where('tenant_id', $tenantId),
            ],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:3072'],
            'item_type' => ['required', Rule::in(self::ITEM_TYPES)],
            'item_jenis_id' => ['required', 'integer', Rule::exists('item_jenises', 'id')->where('tenant_id', $tenantId)->where('is_active', true)],
            'item_category_id' => ['nullable', 'integer', Rule::exists('item_categories', 'id')->where('tenant_id', $tenantId)->where('is_active', true)],
            'keterangan_pembeda' => ['nullable', 'string', 'max:255'],
            'base_unit_id' => ['required', 'integer', $this->unitExistsRule($tenantId)],
            'inventory_unit_id' => ['nullable', 'integer', $this->unitExistsRule($tenantId)],
            'purchase_unit_id' => ['nullable', 'integer', $this->unitExistsRule($tenantId)],
            'inventory_ratio' => ['required_with:inventory_unit_id', 'nullable', Decimal::validationRule(), $this->decimalMinRule('0.0001')],
            'purchase_ratio' => ['required_with:purchase_unit_id', 'nullable', Decimal::validationRule(), $this->decimalMinRule('0.0001')],
            'yield_pct' => ['nullable', Decimal::validationRule(2), $this->decimalBetweenRule('0', '100')],
            'last_purchase_price' => ['nullable', Decimal::validationRule(4), $this->decimalMinRule('0')],
            'primary_department_id' => ['nullable', 'integer', $this->departmentExistsRule($tenantId)],
            'opname_frequency' => ['required', Rule::in(['DAILY', 'WEEKLY', 'MONTHLY'])],
            'track_expiry' => ['boolean'],
            'department_ids' => ['nullable', 'array'],
            'department_ids.*' => ['integer', $this->departmentExistsRule($tenantId)],
            'outlet_ids' => ['nullable', 'array'],
            'outlet_ids.*' => ['integer', $this->outletExistsRule($tenantId)],
            'sync_extra_conversions' => ['boolean'],
            'extra_conversions' => ['nullable', 'array'],
            'extra_conversions.*.from_unit_id' => ['required_with:extra_conversions', 'integer', $this->unitExistsRule($tenantId)],
            'extra_conversions.*.to_unit_id' => ['required_with:extra_conversions', 'integer', $this->unitExistsRule($tenantId)],
            'extra_conversions.*.factor' => ['required_with:extra_conversions', Decimal::validationRule(8), $this->decimalMinRule('0.00000001')],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        $baseUnitId = $this->integer('base_unit_id');
        $inventoryUnitId = $this->filled('inventory_unit_id') ? $this->integer('inventory_unit_id') : $baseUnitId;
        $purchaseUnitId = $this->filled('purchase_unit_id') ? $this->integer('purchase_unit_id') : null;
        $lastPurchasePrice = $this->filled('last_purchase_price')
            ? Decimal::toFixed($this->input('last_purchase_price'), 4)
            : null;

        return [
            'tenant_id' => (int) $this->user()->tenant_id,
            'canonical_sku' => $this->string('canonical_sku')->toString(),
            'name' => $this->string('name')->toString(),
            'description' => $this->input('description'),
            'keterangan_pembeda' => $this->input('keterangan_pembeda'),
            'item_type' => $this->string('item_type')->toString(),
            'item_jenis_id' => $this->integer('item_jenis_id'),
            'item_category_id' => $this->filled('item_category_id') ? $this->integer('item_category_id') : null,
            'base_unit_id' => $baseUnitId,
            'inventory_unit_id' => $inventoryUnitId,
            'purchase_unit_id' => $purchaseUnitId,
            'inventory_ratio' => $this->filled('inventory_unit_id') ? Decimal::toFixed($this->input('inventory_ratio')) : '1.000000',
            'purchase_ratio' => $purchaseUnitId ? Decimal::toFixed($this->input('purchase_ratio')) : null,
            'yield_pct' => $this->filled('yield_pct') ? Decimal::toFixed($this->input('yield_pct'), 2) : null,
            'opname_frequency' => $this->string('opname_frequency')->toString(),
            'primary_department_id' => $this->filled('primary_department_id') ? $this->integer('primary_department_id') : null,
            'track_expiry' => $this->boolean('track_expiry'),
            'last_purchase_price' => $lastPurchasePrice,
            'standard_cost' => $lastPurchasePrice ?? '0.0000',
            'track_stock' => true,
            'is_active' => $this->boolean('is_active'),
        ];
    }

    protected function unitExistsRule(int $tenantId): mixed
    {
        return Rule::exists('units', 'id')->where('tenant_id', $tenantId);
    }

    protected function departmentExistsRule(int $tenantId): mixed
    {
        return Rule::exists('departments', 'id')->where('tenant_id', $tenantId);
    }

    protected function outletExistsRule(int $tenantId): mixed
    {
        return Rule::exists('outlets', 'id')->where('tenant_id', $tenantId);
    }

    protected function decimalMinRule(string $minimum): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($minimum): void {
            if ($value === null || $value === '') {
                return;
            }

            try {
                $normalized = Decimal::normalize($value, 8);
            } catch (\InvalidArgumentException) {
                return;
            }

            if ((float) $normalized < (float) $minimum) {
                $fail("The {$attribute} field must be at least {$minimum}.");
            }
        };
    }

    protected function decimalBetweenRule(string $minimum, string $maximum): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($minimum, $maximum): void {
            if ($value === null || $value === '') {
                return;
            }

            try {
                $normalized = Decimal::normalize($value, 8);
            } catch (\InvalidArgumentException) {
                return;
            }

            if ((float) $normalized < (float) $minimum || (float) $normalized > (float) $maximum) {
                $fail("The {$attribute} field must be between {$minimum} and {$maximum}.");
            }
        };
    }
}
