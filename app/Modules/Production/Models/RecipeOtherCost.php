<?php

namespace App\Modules\Production\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecipeOtherCost extends Model
{
    protected $guarded = [];

    const TYPE_PRODUCTION = 'PRODUCTION';
    const TYPE_OVERHEAD   = 'OVERHEAD';

    const TYPE_LABELS = [
        self::TYPE_PRODUCTION => 'Biaya Produksi',
        self::TYPE_OVERHEAD   => 'Biaya Overhead',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:4',
        ];
    }

    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class);
    }
}
