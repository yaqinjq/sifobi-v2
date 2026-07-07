<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $tenantId = auth()->user()?->tenant_id;

        if (! $tenantId) {
            return;
        }

        $builder->where($model->getTable().'.tenant_id', $tenantId);
    }
}
