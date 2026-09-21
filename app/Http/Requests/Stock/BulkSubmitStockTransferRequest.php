<?php

namespace App\Http\Requests\Stock;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkSubmitStockTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create_stock_transfers') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $tenantId = (int) $this->user()->tenant_id;

        return [
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', Rule::exists('stock_transfers', 'id')->where('tenant_id', $tenantId)],
        ];
    }
}
