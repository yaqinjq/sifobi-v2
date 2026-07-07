<?php

namespace App\Modules\Core\Models;

use App\Models\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Model;

class IntegrationProfile extends Model
{
    protected $fillable = [
        'tenant_id',
        'code',
        'name',
        'provider',
        'base_url',
        'auth_mode',
        'auth_token',
        'auth_username',
        'auth_password',
        'auth_type',
        'api_token',
        'username',
        'password',
        'outlet_sync_path',
        'meta',
        'is_active',
        'last_synced_at',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope);
    }

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'is_active' => 'boolean',
            'last_synced_at' => 'datetime',
        ];
    }
}
