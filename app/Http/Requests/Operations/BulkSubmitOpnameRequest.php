<?php

namespace App\Http\Requests\Operations;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkSubmitOpnameRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('input_opname') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $tenantId = (int) $this->user()->tenant_id;

        return [
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', Rule::exists('opname_sessions', 'id')->where('tenant_id', $tenantId)],
        ];
    }
}
