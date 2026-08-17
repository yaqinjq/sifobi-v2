<?php

namespace App\Modules\Core\Models;

use App\Models\Scopes\TenantScope;
use Spatie\Activitylog\Models\Activity;

class ActivityLogEntry extends Activity
{
    protected static function booted(): void
    {
        static::creating(function (ActivityLogEntry $entry): void {
            if ($entry->tenant_id) {
                return;
            }

            $tenantId = $entry->causer?->tenant_id
                ?? $entry->subject?->tenant_id
                ?? auth()->user()?->tenant_id;

            if ($tenantId) {
                $entry->tenant_id = $tenantId;
            }
        });

        static::addGlobalScope(new TenantScope);
    }
}
