<?php

namespace App\Modules\Inventory\Models;

use App\Models\Scopes\TenantScope;
use App\Modules\Core\Models\Department;
use App\Modules\Core\Models\Outlet;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Item extends Model
{
    use LogsActivity;

    protected $fillable = [
        'tenant_id',
        'item_category_id',
        'inventory_unit_id',
        'purchase_unit_id',
        'base_unit_id',
        'inventory_ratio',
        'purchase_ratio',
        'yield_pct',
        'last_purchase_price',
        'canonical_sku',
        'name',
        'description',
        'photo',
        'keterangan_pembeda',
        'item_type',
        'item_jenis_id',
        'opname_frequency',
        'primary_department_id',
        'track_expiry',
        'barcode',
        'standard_cost',
        'min_stock',
        'track_stock',
        'is_active',
        'po_destinations',
        'item_source',
    ];

    /**
     * Filter items available for a given PO type.
     * Items with no po_destinations appear in all tabs (catch-all).
     */
    public function scopeForPoType(\Illuminate\Database\Eloquent\Builder $query, string $poType): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where(function ($q) use ($poType) {
            $q->whereNull('po_destinations')
              ->orWhereJsonContains('po_destinations', $poType);
        });
    }

    public function inventoryUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'inventory_unit_id');
    }

    public function purchaseUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'purchase_unit_id');
    }

    public function baseUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'base_unit_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ItemCategory::class, 'item_category_id');
    }

    public function primaryDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'primary_department_id');
    }

    public function jenis(): BelongsTo
    {
        return $this->belongsTo(ItemJenis::class, 'item_jenis_id');
    }

    public function itemJenis(): BelongsTo
    {
        return $this->jenis();
    }

    public function departments(): BelongsToMany
    {
        return $this->belongsToMany(
            Department::class,
            'item_departments',
            'item_id',
            'department_id'
        )->withTimestamps();
    }

    public function outlets(): BelongsToMany
    {
        return $this->belongsToMany(
            Outlet::class,
            'item_outlets',
            'item_id',
            'outlet_id'
        )->withPivot('tenant_id', 'status', 'opname_frequency')
            ->withTimestamps();
    }

    public function conversions(): HasMany
    {
        return $this->hasMany(UnitConversion::class);
    }

    public function brandAliases(): HasMany
    {
        return $this->hasMany(ItemBrandAlias::class, 'item_id');
    }

    public function stockConfigs(): HasMany
    {
        return $this->hasMany(ItemStockConfig::class, 'item_id');
    }

    public function stockConfigForOutlet(int $outletId): ?ItemStockConfig
    {
        return $this->stockConfigs->firstWhere('outlet_id', $outletId);
    }

    public function aliasByBrand(int $brandId): ?ItemBrandAlias
    {
        return $this->brandAliases->firstWhere('brand_id', $brandId);
    }

    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('master-data')
            ->logFillable()
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    protected function casts(): array
    {
        return [
            'standard_cost'      => 'decimal:4',
            'min_stock'          => 'decimal:6',
            'inventory_ratio'    => 'decimal:6',
            'purchase_ratio'     => 'decimal:6',
            'yield_pct'          => 'decimal:2',
            'last_purchase_price' => 'decimal:4',
            'track_expiry'       => 'boolean',
            'track_stock'        => 'boolean',
            'is_active'          => 'boolean',
            'po_destinations'    => 'array',
            'item_source'        => 'string',
        ];
    }
}
