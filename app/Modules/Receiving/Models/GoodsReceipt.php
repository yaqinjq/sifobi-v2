<?php

namespace App\Modules\Receiving\Models;

use App\Models\Scopes\TenantScope;
use App\Models\User;
use App\Modules\Core\Models\Outlet;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class GoodsReceipt extends Model
{
    use SoftDeletes;

    public const SOURCE_OCIA_PO = 'OCIA_PO';
    public const SOURCE_WIP_CENTRAL_KITCHEN = 'WIP_CENTRAL_KITCHEN';
    public const SOURCE_PURCHASING_DRYGOOD = 'PURCHASING_DRYGOOD';
    public const SOURCE_SUPPLIER_LUAR = 'SUPPLIER_LUAR';

    public const STATUS_DRAFT = 'DRAFT';
    public const STATUS_SUBMITTED = 'SUBMITTED';
    public const STATUS_APPROVED = 'APPROVED';
    public const STATUS_REJECTED = 'REJECTED';
    public const STATUS_POSTED = 'POSTED';

    public const REVIEW_NONE = 'NONE';
    public const REVIEW_NEED_REVIEW = 'NEED_REVIEW';
    public const REVIEW_APPROVED = 'APPROVED';
    public const REVIEW_REJECTED = 'REJECTED';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'code',
        'receipt_number',
        'outlet_id',
        'source',
        'source_type',
        'source_reference',
        'external_po_number',
        'supplier_id',
        'supplier_name',
        'vendor_name',
        'doc_number',
        'invoice_number',
        'photo_document',
        'receipt_date',
        'received_at',
        'status',
        'review_status',
        'created_by',
        'received_by',
        'submitted_by',
        'reviewed_by',
        'reviewed_at',
        'approved_by',
        'approved_at',
        'review_notes',
        'notes',
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
            'receipt_date' => 'date',
            'received_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(GoodsReceiptItem::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    protected function statusBadgeClass(): Attribute
    {
        return Attribute::get(fn (): string => [
            self::STATUS_DRAFT => 'badge-draft',
            self::STATUS_SUBMITTED => 'badge-pending',
            self::STATUS_APPROVED => 'badge-approved',
            self::STATUS_REJECTED => 'badge-rejected',
            self::STATUS_POSTED => 'badge-posted',
        ][$this->status] ?? 'badge-draft');
    }

    protected function sourceBadgeClass(): Attribute
    {
        return Attribute::get(fn (): string => [
            self::SOURCE_OCIA_PO => 'inline-flex items-center rounded-full text-xs font-semibold px-2.5 py-0.5 bg-amber-100 text-amber-800',
            self::SOURCE_WIP_CENTRAL_KITCHEN => 'inline-flex items-center rounded-full text-xs font-semibold px-2.5 py-0.5 bg-orange-100 text-orange-800',
            self::SOURCE_PURCHASING_DRYGOOD => 'inline-flex items-center rounded-full text-xs font-semibold px-2.5 py-0.5 bg-blue-100 text-blue-800',
            self::SOURCE_SUPPLIER_LUAR => 'inline-flex items-center rounded-full text-xs font-semibold px-2.5 py-0.5 bg-purple-100 text-purple-800',
        ][$this->source] ?? 'badge-draft');
    }

    protected function sourceLabel(): Attribute
    {
        return Attribute::get(fn (): string => [
            self::SOURCE_OCIA_PO => 'Kopi (OCIA)',
            self::SOURCE_WIP_CENTRAL_KITCHEN => 'WIP Central Kitchen',
            self::SOURCE_PURCHASING_DRYGOOD => 'Drygood (Purchasing)',
            self::SOURCE_SUPPLIER_LUAR => 'Supplier Luar',
        ][$this->source] ?? (string) $this->source);
    }
}
