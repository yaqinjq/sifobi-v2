<?php

namespace App\Models;

use App\Models\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Model;

class AppSetting extends Model
{
    protected $fillable = [
        'tenant_id',
        'app_name',
        'app_tagline',
        'logo_path',
        'favicon_path',
        'primary_color',
        'contact_email',
        'contact_phone',
        'smtp_host',
        'smtp_port',
        'smtp_username',
        'smtp_password',
        'smtp_encryption',
        'smtp_from_address',
        'smtp_from_name',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope);
    }

    public static function current(): self
    {
        $tenantId = auth()->user()?->tenant_id ?? 1;
        $appName = config('app.name', 'SIFOBI');

        return static::query()->firstOrCreate(
            ['tenant_id' => $tenantId],
            [
                'app_name' => $appName === 'Laravel' ? 'SIFOBI' : $appName,
                'app_tagline' => 'Food & Beverage Inventory System',
            ]
        );
    }
}
