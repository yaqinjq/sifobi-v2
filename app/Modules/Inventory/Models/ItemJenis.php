<?php

namespace App\Modules\Inventory\Models;

use App\Models\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ItemJenis extends Model
{
    protected $table = 'item_jenises';

    public const COLORS = [
        'gray',
        'green',
        'blue',
        'amber',
        'red',
        'purple',
        'rose',
        'indigo',
    ];

    protected $fillable = [
        'tenant_id',
        'code',
        'name',
        'color',
        'description',
        'is_active',
        'sort_order',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(Item::class, 'item_jenis_id');
    }

    public function badgeClass(): string
    {
        return match ($this->color) {
            'green' => 'badge-active',
            'blue' => 'badge-blue',
            'amber' => 'badge-pending',
            'red' => 'badge-rejected',
            'purple' => 'badge-purple',
            'rose' => 'badge-rose',
            'indigo' => 'badge-indigo',
            default => 'badge-draft',
        };
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
