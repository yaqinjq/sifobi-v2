<?php

namespace App\Http\Requests\Operations;

use App\Modules\Operations\Models\OpenStock;
use App\Support\Decimal;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOpenStockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('input_open_stock') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'outlet_id' => ['required', 'integer', 'exists:outlets,id'],
            'item_id' => ['required', 'integer', 'exists:items,id'],
            'stock_target' => ['required', 'string', Rule::in(array_keys(OpenStock::targetOptions()))],
            'business_date' => ['required', 'date'],
            'qty_whole' => ['required', Decimal::validationRule()],
            'qty_loose' => ['required', Decimal::validationRule()],
            'cost_per_unit' => ['nullable', Decimal::validationRule(4)],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return [
            'tenant_id' => $this->user()->tenant_id,
            'outlet_id' => $this->integer('outlet_id'),
            'item_id' => $this->integer('item_id'),
            'stock_target' => $this->string('stock_target')->toString(),
            'business_date' => $this->date('business_date')->toDateString(),
            'qty_whole' => Decimal::toFixed($this->input('qty_whole')),
            'qty_loose' => Decimal::toFixed($this->input('qty_loose')),
            'cost_per_unit' => $this->filled('cost_per_unit') ? Decimal::toFixed($this->input('cost_per_unit'), 4) : null,
            'created_by' => $this->user()->id,
            'notes' => $this->input('notes'),
        ];
    }
}

