<?php

namespace App\Modules\Procurement\Models;

use App\Models\Scopes\TenantScope;
use App\Models\User;
use App\Modules\Core\Models\Department;
use App\Modules\Core\Models\IntegrationProfile;
use App\Modules\Core\Models\Outlet;
use App\Modules\Receiving\Models\GoodsReceipt;
use App\Modules\Receiving\Models\Supplier;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseOrder extends Model
{

    protected $guarded = [];

    const STATUS_DRAFT     = 'DRAFT';
    const STATUS_SUBMITTED = 'SUBMITTED';
    const STATUS_APPROVED  = 'APPROVED';
    const STATUS_SENT      = 'SENT';
    const STATUS_SHIPPED   = 'SHIPPED';
    const STATUS_CLOSED    = 'CLOSED';
    const STATUS_REJECTED  = 'REJECTED';

    const TYPE_OCIA_ROASTERY   = 'OCIA_ROASTERY';
    const TYPE_CENTRAL_KITCHEN = 'CENTRAL_KITCHEN';
    const TYPE_DRYGOOD         = 'DRYGOOD';

    const TYPE_LABELS = [
        self::TYPE_OCIA_ROASTERY   => 'OCIA / Roastery',
        self::TYPE_CENTRAL_KITCHEN => 'Central Kitchen (Wipro)',
        self::TYPE_DRYGOOD         => 'Drygood / Purchasing',
    ];

    const TYPE_LABELS_ACTIVE = [
        self::TYPE_OCIA_ROASTERY   => 'OCIA / Roastery',
        self::TYPE_CENTRAL_KITCHEN => 'Central Kitchen (Wipro)',
    ];

    const STATUS_LABELS = [
        self::STATUS_DRAFT     => 'Draft',
        self::STATUS_SUBMITTED => 'Diajukan',
        self::STATUS_APPROVED  => 'Disetujui',
        self::STATUS_SENT      => 'Terkirim ke Vendor',
        self::STATUS_SHIPPED   => 'Dikirim Vendor',
        self::STATUS_CLOSED    => 'Selesai',
        self::STATUS_REJECTED  => 'Ditolak',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope());
    }

    protected function casts(): array
    {
        return [
            'needed_at'           => 'date',
            'planned_submit_at'   => 'datetime',
            'approved_at'         => 'datetime',
            'submitted_at'        => 'datetime',
            'rejected_at'         => 'datetime',
            'sent_at'             => 'datetime',
            'closed_at'           => 'datetime',
            'external_synced_at'  => 'datetime',
            'wipro_shipped_at'    => 'datetime',
        ];
    }

    public function isPlanOrder(): bool
    {
        return $this->planned_submit_at !== null;
    }

    // ── Relationships ─────────────────────────────────────────────────────

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function integrationProfile(): BelongsTo
    {
        return $this->belongsTo(IntegrationProfile::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function rejectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function sentBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function approvalEvents(): HasMany
    {
        return $this->hasMany(PurchaseOrderApprovalEvent::class)->orderBy('created_at');
    }

    public function goodsReceipts(): HasMany
    {
        return $this->hasMany(GoodsReceipt::class);
    }

    public function shipments(): HasMany
    {
        return $this->hasMany(PurchaseOrderShipment::class)->orderBy('id');
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    public function typeLabel(): string
    {
        return self::TYPE_LABELS[$this->po_type] ?? $this->po_type;
    }

    public function statusBadgeClass(): string
    {
        return match ($this->status) {
            self::STATUS_DRAFT     => 'badge-draft',
            self::STATUS_SUBMITTED => 'badge-pending',
            self::STATUS_APPROVED  => 'badge-active',
            self::STATUS_SENT      => 'badge-posted',
            self::STATUS_SHIPPED   => 'badge-info',
            self::STATUS_CLOSED    => 'badge-posted',
            self::STATUS_REJECTED  => 'badge-void',
            default                => 'badge-draft',
        };
    }

    public function canEdit(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function canSubmit(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function canApprove(): bool
    {
        return $this->status === self::STATUS_SUBMITTED;
    }

    public function canSend(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function canClose(): bool
    {
        return in_array($this->status, [self::STATUS_SHIPPED, self::STATUS_SENT]);
    }

    public function canReject(): bool
    {
        return in_array($this->status, [self::STATUS_SUBMITTED, self::STATUS_APPROVED]);
    }
}
