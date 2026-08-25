<?php

namespace App\Modules\Production\Models;

use App\Models\Scopes\TenantScope;
use App\Modules\Core\Models\Brand;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Menu extends Model
{
    use LogsActivity;

    protected $fillable = [
        'tenant_id',
        'brand_id',
        'menu_category_id',
        'code',
        'name',
        'photo_path',
        'selling_price',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'selling_price' => 'decimal:4',
            'is_active' => 'boolean',
        ];
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(MenuCategory::class, 'menu_category_id');
    }

    public function photoUrl(): ?string
    {
        return $this->photo_path ? Storage::url($this->photo_path) : null;
    }

    public function recipes(): HasMany
    {
        return $this->hasMany(Recipe::class)->orderByDesc('version_number');
    }

    public function approvedRecipes(): HasMany
    {
        return $this->recipes()->where('status', Recipe::STATUS_APPROVED);
    }

    public function canHardDelete(): bool
    {
        return $this->recipes()->doesntExist();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('production')
            ->logFillable()
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope);
    }
}
