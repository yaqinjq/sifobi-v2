<?php

namespace App\Modules\Procurement\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseOrder extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'needed_at' => 'date',
            'approved_at' => 'datetime',
        ];
    }
}

