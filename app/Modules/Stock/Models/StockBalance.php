<?php

namespace App\Modules\Stock\Models;

use App\Models\Scopes\TenantScope;
use App\Modules\Core\Models\Outlet;
use App\Modules\Inventory\Models\Item;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockBalance extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'outlet_id',
        'item_id',
        'stock_target',
        'qty_on_hand',
        'avg_cost',
        'total_value',
        'last_mutation_id',
        'last_mutation_at',
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

    public function lastMutation(): BelongsTo
    {
        return $this->belongsTo(StockMutation::class, 'last_mutation_id');
    }

    public function getQtyWholeAttribute(): float
    {
        $ratio = (float) ($this->item?->inventory_ratio ?? 1);

        if ($ratio <= 0) {
            return (float) $this->qty_on_hand;
        }

        return floor((float) $this->qty_on_hand / $ratio);
    }

    public function getQtyLooseAttribute(): float
    {
        $ratio = (float) ($this->item?->inventory_ratio ?? 1);

        if ($ratio <= 0) {
            return 0.0;
        }

        return fmod((float) $this->qty_on_hand, $ratio);
    }

    public function getStockStatusAttribute(): string
    {
        return (float) $this->qty_on_hand <= 0 ? 'EMPTY' : 'OK';
    }

    protected function casts(): array
    {
        return [
            'qty_on_hand' => 'decimal:6',
            'avg_cost' => 'decimal:4',
            'total_value' => 'decimal:4',
            'last_mutation_at' => 'datetime',
        ];
    }
}
