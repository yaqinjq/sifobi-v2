<?php

namespace App\Modules\Stock\Models;

use App\Modules\Inventory\Models\Item;
use App\Modules\Inventory\Models\Unit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockTransferItem extends Model
{
    protected $guarded = [];

    public function transfer(): BelongsTo
    {
        return $this->belongsTo(StockTransfer::class, 'stock_transfer_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    protected function casts(): array
    {
        return [
            'qty'              => 'decimal:6',
            'qty_in_base_unit' => 'decimal:6',
        ];
    }
}
