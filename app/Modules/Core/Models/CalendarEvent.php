<?php

namespace App\Modules\Core\Models;

use App\Models\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CalendarEvent extends Model
{
    public const TYPES = [
        'HARI_RAYA',
        'PROMO',
        'LIBURAN',
        'PEAK_SEASON',
        'CUSTOM',
    ];

    protected $fillable = [
        'tenant_id',
        'outlet_id',
        'brand_id',
        'name',
        'event_date',
        'event_end_date',
        'event_type',
        'demand_multiplier',
        'notes',
        'is_active',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope);
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    protected function casts(): array
    {
        return [
            'event_date' => 'date',
            'event_end_date' => 'date',
            'demand_multiplier' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }
}
