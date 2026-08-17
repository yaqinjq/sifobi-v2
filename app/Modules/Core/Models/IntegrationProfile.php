<?php

namespace App\Modules\Core\Models;

use App\Models\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class IntegrationProfile extends Model
{
    use LogsActivity;


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

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('master-data')
            ->logFillable()
            // Kredensial integrasi jangan pernah masuk log — cukup catat bahwa
            // profilnya berubah, bukan isi token/password-nya.
            ->logExcept(['auth_token', 'auth_username', 'auth_password', 'api_token', 'username', 'password'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
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
