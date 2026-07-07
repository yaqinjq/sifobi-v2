<?php

namespace App\Modules\Core\Models;

use App\Models\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Brand extends Model
{
    protected $fillable = [
        'tenant_id',
        'group_id',
        'code',
        'name',
        'logo_path',
        'description',
        'status',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function outlets(): HasMany
    {
        return $this->hasMany(Outlet::class);
    }

    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope);
    }
}
