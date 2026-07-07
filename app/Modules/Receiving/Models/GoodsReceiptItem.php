<?php

namespace App\Modules\Receiving\Models;

use App\Modules\Inventory\Models\Item;
use App\Modules\Inventory\Models\Unit;
use App\Modules\Stock\Models\StockMutation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoodsReceiptItem extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'goods_receipt_id',
        'item_id',
        'unit_id',
        'qty_ordered',
        'qty_received',
        'qty_in_base_unit',
        'qty_short',
        'qty_over',
        'unit_price',
        'unit_cost',
        'total_value',
        'item_status',
        'expired_date',
        'batch_code',
        'mutation_id',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'qty_ordered' => 'decimal:6',
            'qty_received' => 'decimal:6',
            'qty_in_base_unit' => 'decimal:6',
            'qty_short' => 'decimal:6',
            'qty_over' => 'decimal:6',
            'unit_price' => 'decimal:4',
            'unit_cost' => 'decimal:4',
            'total_value' => 'decimal:4',
            'expired_date' => 'date',
        ];
    }

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(GoodsReceipt::class, 'goods_receipt_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function mutation(): BelongsTo
    {
        return $this->belongsTo(StockMutation::class, 'mutation_id');
    }
}
