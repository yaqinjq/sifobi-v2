<?php

namespace App\Modules\Pos\Models;

use App\Models\Scopes\TenantScope;
use App\Models\User;
use App\Modules\Core\Models\Outlet;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class PosShift extends Model
{
    use LogsActivity;

    public const STATUS_OPEN = 'OPEN';
    public const STATUS_CLOSED = 'CLOSED';
    public const STATUS_RECONCILED = 'RECONCILED';

    public const STATUS_LABELS = [
        self::STATUS_OPEN => 'Berjalan',
        self::STATUS_CLOSED => 'Ditutup',
        self::STATUS_RECONCILED => 'Direkonsiliasi',
    ];

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'opening_cash' => 'decimal:4',
            'closing_cash_expected' => 'decimal:4',
            'closing_cash_actual' => 'decimal:4',
            'variance' => 'decimal:4',
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
            'reconciled_at' => 'datetime',
        ];
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }

    public function openedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function reconciledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reconciled_by');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(PosPayment::class);
    }

    public function canClose(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }

    public function canReconcile(): bool
    {
        return $this->status === self::STATUS_CLOSED;
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    public function statusBadgeClass(): string
    {
        return match ($this->status) {
            self::STATUS_OPEN => 'badge-pending',
            self::STATUS_CLOSED => 'badge-draft',
            self::STATUS_RECONCILED => 'badge-active',
            default => 'badge-draft',
        };
    }

    public function varianceBadgeClass(): string
    {
        if ($this->variance === null) {
            return 'text-gray-400';
        }

        return match (true) {
            bccomp((string) $this->variance, '0', 4) === 0 => 'text-primary-700',
            bccomp((string) $this->variance, '0', 4) > 0 => 'text-blue-600',
            default => 'text-red-600',
        };
    }

    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('pos')
            ->logOnly(['status', 'outlet_id', 'opening_cash', 'closing_cash_expected', 'closing_cash_actual', 'variance'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
