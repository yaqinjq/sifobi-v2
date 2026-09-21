<?php

namespace App\Http\Requests\MasterData;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkToggleItemStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage_items') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $tenantId = (int) $this->user()->tenant_id;

        return [
            'active' => ['required', 'boolean'],
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', Rule::exists('items', 'id')->where('tenant_id', $tenantId)],
        ];
    }
}
