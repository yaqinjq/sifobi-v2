<?php

namespace App\Modules\Procurement\Models;

use App\Modules\Inventory\Models\Item;
use App\Modules\Inventory\Models\Unit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrderItem extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'qty_ordered' => 'decimal:6',
            'unit_cost'   => 'decimal:4',
        ];
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function subtotal(): string
    {
        if (! $this->unit_cost) {
            return '0';
        }

        return bcmul((string) $this->qty_ordered, (string) $this->unit_cost, 4);
    }
}
