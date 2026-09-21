<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkDestroyCalendarEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage_calendar_events') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $tenantId = (int) $this->user()->tenant_id;

        return [
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', Rule::exists('calendar_events', 'id')->where('tenant_id', $tenantId)],
        ];
    }
}
