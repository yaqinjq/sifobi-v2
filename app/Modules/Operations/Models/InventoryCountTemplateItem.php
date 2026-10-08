<?php

namespace App\Modules\Operations\Models;

use App\Models\Scopes\TenantScope;
use App\Modules\Inventory\Models\Item;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu baris konfigurasi item di dalam 1 InventoryCountTemplate -- item_id
 * WAJIB merujuk ke master Item existing (bukan pencocokan nama), supaya
 * konsisten dengan seluruh sistem SIFOBI dan tidak rapuh seperti
 * carry-forward by-nama di form kertas Ah Pek asli.
 */
class InventoryCountTemplateItem extends Model
{
    protected $table = 'inventory_count_template_items';

    protected $fillable = [
        'tenant_id',
        'template_id',
        'item_id',
        'subcategory_label',
        'sort_order',
        'locations',
        'requires_conversion',
        'is_active',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope);
    }

    protected function casts(): array
    {
        return [
            'locations' => 'array',
            'requires_conversion' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(InventoryCountTemplate::class, 'template_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
