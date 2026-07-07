<?php

namespace App\Modules\Procurement\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseOrderItem extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'qty_ordered' => 'decimal:6',
            'unit_cost' => 'decimal:4',
        ];
    }
}

