<?php

namespace App\Modules\Inventory\Models;

use App\Models\Scopes\TenantScope;
use App\Modules\Core\Models\Brand;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItemBrandAlias extends Model
{
    protected $fillable = [
        'tenant_id',
        'item_id',
        'brand_id',
        'brand_sku',
        'brand_item_name',
        'is_primary',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope);
    }

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
        ];
    }
}
