<?php

namespace App\Modules\Stock\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

class StockMutation extends Model
{
    public const TYPE_OPEN_STOCK = 'OPEN_STOCK';
    public const TYPE_GOODS_RECEIVE = 'GOODS_RECEIVE';
    public const TYPE_PO_RECEIVE = 'PO_RECEIVE';
    public const TYPE_SPOIL_WASTE = 'SPOIL_WASTE';
    public const TYPE_DAILY_OPNAME_ADJ = 'DAILY_OPNAME_ADJ';
    public const TYPE_MONTHLY_OPNAME_ADJ = 'MONTHLY_OPNAME_ADJ';
    public const TYPE_VOID_REVERSAL = 'VOID_REVERSAL';

    public const TARGET_OUTLET_DAILY = 'OUTLET_DAILY';
    public const TARGET_OUTLET_WAREHOUSE = 'OUTLET_WAREHOUSE';

    protected $guarded = [];

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new LogicException('Stock mutations are immutable. Create a reversal mutation instead.');
        });

        static::deleting(function (): void {
            throw new LogicException('Stock mutations are immutable. Create a reversal mutation instead.');
        });
    }

    protected function casts(): array
    {
        return [
            'qty_change' => 'decimal:6',
            'balance_after' => 'decimal:6',
            'metadata' => 'array',
            'performed_at' => 'datetime',
        ];
    }
}
