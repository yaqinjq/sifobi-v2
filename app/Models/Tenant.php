<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Tenant extends Model
{
    use LogsActivity;

    protected $fillable = [
        'code',
        'name',
        'status',
        'subdomain',
        'custom_domain',
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
        ];
    }

    public function fullSubdomainHost(): ?string
    {
        if (! $this->subdomain) {
            return null;
        }

        return "{$this->subdomain}.".config('app.base_domain');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('master-data')
            ->logOnly(['code', 'name', 'status', 'subdomain', 'custom_domain'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
