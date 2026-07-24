<?php

namespace App\Modules\Stock\Models;

use App\Models\Scopes\TenantScope;
use App\Modules\Core\Models\Outlet;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockTransfer extends Model
{
    public const STATUS_DRAFT     = 'DRAFT';
    public const STATUS_SUBMITTED = 'SUBMITTED';
    public const STATUS_APPROVED  = 'APPROVED';
    public const STATUS_REJECTED  = 'REJECTED';
    public const STATUS_VOIDED    = 'VOIDED';

    protected $guarded = [];

    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope);
    }

    public function fromOutlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class, 'from_outlet_id');
    }

    public function toOutlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class, 'to_outlet_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockTransferItem::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'submitted_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'approved_by');
    }

    public function rejectedBy(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'rejected_by');
    }

    public function voidedBy(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'voided_by');
    }

    public function statusBadgeClass(): string
    {
        return [
            self::STATUS_DRAFT     => 'badge-draft',
            self::STATUS_SUBMITTED => 'badge-pending',
            self::STATUS_APPROVED  => 'badge-approved',
            self::STATUS_REJECTED  => 'badge-rejected',
            self::STATUS_VOIDED    => 'badge-void',
        ][$this->status] ?? 'badge-draft';
    }

    public function statusLabel(): string
    {
        return [
            self::STATUS_DRAFT     => 'Draft',
            self::STATUS_SUBMITTED => 'Menunggu Approval',
            self::STATUS_APPROVED  => 'Disetujui',
            self::STATUS_REJECTED  => 'Ditolak',
            self::STATUS_VOIDED    => 'Dibatalkan',
        ][$this->status] ?? $this->status;
    }

    protected function casts(): array
    {
        return [
            'transfer_date' => 'date',
            'submitted_at'  => 'datetime',
            'approved_at'   => 'datetime',
            'rejected_at'   => 'datetime',
            'voided_at'     => 'datetime',
        ];
    }
}
