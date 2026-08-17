<?php

namespace App\Models;

use App\Models\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class AppSetting extends Model
{
    use LogsActivity;


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

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('master-data')
            ->logFillable()
            ->logExcept(['smtp_password'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    public static function current(): self
    {
        return static::forTenant((int) (auth()->user()?->tenant_id ?? 1));
    }

    /**
     * Sama seperti current(), tapi tidak bergantung pada auth() — dipakai
     * dari konteks tanpa request/session (mis. queued job) yang perlu
     * eksplisit tahu tenant mana, bukan menebak dari user yang sedang login.
     */
    public static function forTenant(int $tenantId): self
    {
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
