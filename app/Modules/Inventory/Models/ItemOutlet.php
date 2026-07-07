<?php

namespace App\Modules\Inventory\Models;

use App\Modules\Core\Models\Outlet;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItemOutlet extends Model
{
    protected $guarded = [];

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    protected function casts(): array
    {
        return [
            'min_stock' => 'decimal:6',
            'max_stock' => 'decimal:6',
            'par_stock' => 'decimal:6',
            'reorder_point' => 'decimal:6',
            'is_active' => 'boolean',
        ];
    }
}
