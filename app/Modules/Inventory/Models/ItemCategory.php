<?php

namespace App\Modules\Inventory\Models;

use App\Models\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ItemCategory extends Model
{
    protected $fillable = [
        'tenant_id',
        'parent_id',
        'code',
        'name',
        'description',
        'status',
        'is_active',
        'sort_order',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(Item::class, 'item_category_id');
    }

    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope);
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
