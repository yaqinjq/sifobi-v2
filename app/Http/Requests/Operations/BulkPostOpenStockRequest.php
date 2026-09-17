<?php

namespace App\Http\Requests\Operations;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkPostOpenStockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('post_open_stock') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $tenantId = (int) $this->user()->tenant_id;

        return [
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', Rule::exists('open_stocks', 'id')->where('tenant_id', $tenantId)],
        ];
    }
}
