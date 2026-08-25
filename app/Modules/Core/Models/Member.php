<?php

namespace App\Modules\Core\Models;

use App\Models\Scopes\TenantScope;
use App\Modules\Pos\Models\PosOrder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Member extends Model
{
    use LogsActivity;

    protected $fillable = [
        'tenant_id',
        'name',
        'phone',
        'points_balance',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'points_balance' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function pointTransactions(): HasMany
    {
        return $this->hasMany(MemberPointTransaction::class)->latest();
    }

    public function orders(): HasMany
    {
        return $this->hasMany(PosOrder::class);
    }

    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('pos')
            ->logFillable()
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
