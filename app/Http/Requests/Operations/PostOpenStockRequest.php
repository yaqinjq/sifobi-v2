<?php

namespace App\Http\Requests\Operations;

use Illuminate\Foundation\Http\FormRequest;

class PostOpenStockRequest extends FormRequest
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
        return [];
    }
}

