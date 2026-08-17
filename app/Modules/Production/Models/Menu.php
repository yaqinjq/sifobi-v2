<?php

namespace App\Modules\Production\Models;

use App\Models\Scopes\TenantScope;
use App\Modules\Core\Models\Brand;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Menu extends Model
{
    use LogsActivity;

    protected $fillable = [
        'tenant_id',
        'brand_id',
        'code',
        'name',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
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
