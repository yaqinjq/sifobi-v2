<?php

namespace App\Modules\Operations\Models;

use App\Models\Scopes\TenantScope;
use App\Modules\Core\Models\Department;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Template form Inventory Simple untuk 1 departemen (atau generik, kalau
 * department_id null) -- menentukan lokasi apa yang dikenal (Gudang, Bar
 * Area, Kitchen Area, dst) dan item apa saja yang muncul di form itu lewat
 * inventoryCountTemplateItems(). SATU modul untuk semua departemen, template
 * yang membedakan tampilannya -- bukan modul/controller terpisah per
 * departemen.
 */
class InventoryCountTemplate extends Model
{
    public const FREQUENCY_DAILY = 'DAILY';
    public const FREQUENCY_WEEKLY = 'WEEKLY';

    public const STATUS_ACTIVE = 'ACTIVE';
    public const STATUS_INACTIVE = 'INACTIVE';

    protected $table = 'inventory_count_templates';

    protected $fillable = [
        'tenant_id',
        'department_id',
        'code',
        'name',
        'frequency',
        'locations',
        'is_generic',
        'is_configured',
        'status',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope);
    }

    protected function casts(): array
    {
        return [
            'locations' => 'array',
            'is_generic' => 'boolean',
            'is_configured' => 'boolean',
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InventoryCountTemplateItem::class, 'template_id')->orderBy('sort_order');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(InventoryCountDocument::class, 'template_id');
    }
}
