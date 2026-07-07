<?php

namespace App\Modules\Receiving\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentCaptureItem extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'qty' => 'decimal:6',
            'unit_cost' => 'decimal:4',
            'confidence' => 'decimal:4',
            'metadata' => 'array',
        ];
    }
}

