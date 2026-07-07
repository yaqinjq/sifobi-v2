<?php

namespace App\Modules\Receiving\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentCapture extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'captured_at' => 'datetime',
            'metadata' => 'array',
        ];
    }
}

