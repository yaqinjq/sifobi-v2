<?php

namespace App\Modules\Inventory\Models;

use App\Models\Scopes\TenantScope;
use App\Modules\Core\Models\Outlet;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItemStockConfig extends Model
{
    protected $fillable = [
        'tenant_id',
        'item_id',
        'outlet_id',
        'min_stock_qty',
        'max_stock_qty',
        'reorder_point',
        'unit_id',
        'avg_daily_usage_days',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope);
    }

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
            'min_stock_qty' => 'decimal:4',
            'max_stock_qty' => 'decimal:4',
            'reorder_point' => 'decimal:4',
            'avg_daily_usage_days' => 'integer',
        ];
    }
}
