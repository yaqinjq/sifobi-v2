<?php

namespace App\Http\Requests\MasterData;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkActivateWiproItemForOpnameRequest extends FormRequest
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
            'department_id' => ['required', 'integer', Rule::exists('departments', 'id')->where('tenant_id', $tenantId)],
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', Rule::exists('items', 'id')->where(fn ($query) => $query
                ->where('tenant_id', $tenantId)
                ->where('item_source', 'WIPRO'))],
        ];
    }
}
