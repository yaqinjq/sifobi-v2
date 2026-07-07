<?php

namespace App\Modules\Core\Models;

use App\Models\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Outlet extends Model
{
    protected $fillable = [
        'tenant_id',
        'brand_id',
        'legal_entity_id',
        'code',
        'name',
        'outlet_type',
        'timezone',
        'address',
        'contact_phone',
        'status',
    ];

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function legalEntity(): BelongsTo
    {
        return $this->belongsTo(LegalEntity::class);
    }

    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope);
    }
}
