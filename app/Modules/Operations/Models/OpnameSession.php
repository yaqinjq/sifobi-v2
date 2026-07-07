<?php

namespace App\Modules\Operations\Models;

use App\Models\Scopes\TenantScope;
use App\Models\User;
use App\Modules\Core\Models\Department;
use App\Modules\Core\Models\Outlet;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OpnameSession extends Model
{
    public const TYPE_DAILY = 'DAILY';
    public const TYPE_MONTHLY = 'MONTHLY';

    public const STATUS_DRAFT = 'DRAFT';
    public const STATUS_SUBMITTED = 'SUBMITTED';
    public const STATUS_APPROVED = 'APPROVED';
    public const STATUS_PROCESSED = 'PROCESSED';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'outlet_id',
        'department_id',
        'type',
        'opname_type',
        'opname_date',
        'business_date',
        'shift',
        'status',
        'notes',
        'created_by',
        'started_by',
        'submitted_by',
        'approved_by',
        'submitted_at',
        'approved_at',
        'posted_by',
        'posted_at',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'opname_date' => 'date',
            'business_date' => 'date',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'posted_at' => 'datetime',
        ];
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OpnameItem::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    protected function statusBadgeClass(): Attribute
    {
        return Attribute::get(fn (): string => [
            self::STATUS_DRAFT => 'badge-draft',
            self::STATUS_SUBMITTED => 'badge-pending',
            self::STATUS_APPROVED => 'badge-approved',
            self::STATUS_PROCESSED => 'badge-posted',
        ][$this->status] ?? 'badge-draft');
    }
}
