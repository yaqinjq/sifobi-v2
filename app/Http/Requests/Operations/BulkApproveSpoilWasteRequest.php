<?php

namespace App\Http\Requests\Operations;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkApproveSpoilWasteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('approve_spoil') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $tenantId = (int) $this->user()->tenant_id;

        return [
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', Rule::exists('spoil_wastes', 'id')->where('tenant_id', $tenantId)],
        ];
    }
}
