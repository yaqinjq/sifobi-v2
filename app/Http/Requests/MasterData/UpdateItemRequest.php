<?php

namespace App\Http\Requests\MasterData;

use App\Modules\Inventory\Models\Item;
use Illuminate\Validation\Rule;

class UpdateItemRequest extends StoreItemRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = parent::rules();
        $tenantId = (int) $this->user()->tenant_id;
        $item = $this->route('item');

        $rules['canonical_sku'] = [
            'required',
            'string',
            'max:50',
            Rule::unique('items', 'canonical_sku')
                ->where('tenant_id', $tenantId)
                ->ignore($item instanceof Item ? $item->id : null),
        ];

        return $rules;
    }
}
