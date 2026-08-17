<?php

namespace App\Modules\Stock\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class StockMutation extends Model
{
    use LogsActivity;

    /**
     * Ledger ini immutable (lihat guard updating/deleting di booted() di bawah) —
     * activity log hanya boleh mencatat event 'created', tidak pernah
     * 'updated'/'deleted' karena keduanya memang tidak pernah terjadi.
     *
     * @var list<string>
     */
    protected static $recordEvents = ['created'];

    public const TYPE_OPEN_STOCK = 'OPEN_STOCK';
    public const TYPE_GOODS_RECEIVE = 'GOODS_RECEIVE';
    public const TYPE_PO_RECEIVE = 'PO_RECEIVE';
    public const TYPE_SPOIL_WASTE = 'SPOIL_WASTE';
    public const TYPE_DAILY_OPNAME_ADJ = 'DAILY_OPNAME_ADJ';
    public const TYPE_MONTHLY_OPNAME_ADJ = 'MONTHLY_OPNAME_ADJ';
    public const TYPE_VOID_REVERSAL = 'VOID_REVERSAL';
    public const TYPE_TRANSFER_OUT = 'TRANSFER_OUT';
    public const TYPE_TRANSFER_IN = 'TRANSFER_IN';

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

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('stock')
            ->logOnly(['mutation_type', 'stock_target', 'item_id', 'outlet_id', 'qty_change', 'balance_after', 'reference_type', 'reference_id']);
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
