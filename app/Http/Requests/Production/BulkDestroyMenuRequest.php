<?php

namespace App\Http\Requests\Production;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkDestroyMenuRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage_recipes') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $tenantId = (int) $this->user()->tenant_id;

        return [
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', Rule::exists('menus', 'id')->where('tenant_id', $tenantId)],
        ];
    }
}
