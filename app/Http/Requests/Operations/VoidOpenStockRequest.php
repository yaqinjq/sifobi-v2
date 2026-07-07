<?php

namespace App\Http\Requests\Operations;

use Illuminate\Foundation\Http\FormRequest;

class VoidOpenStockRequest extends FormRequest
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
        return [
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reason.required' => 'Alasan void wajib diisi.',
            'reason.min'      => 'Alasan void minimal 5 karakter.',
        ];
    }
}
