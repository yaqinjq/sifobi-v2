<?php

namespace App\Modules\Procurement\Models;

use App\Modules\Receiving\Models\GoodsReceipt;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrderShipment extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'shipped_at'    => 'datetime',
            'confirmed_at'  => 'datetime',
            'is_confirmed'  => 'boolean',
        ];
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function goodsReceipt(): BelongsTo
    {
        return $this->belongsTo(GoodsReceipt::class);
    }
}
