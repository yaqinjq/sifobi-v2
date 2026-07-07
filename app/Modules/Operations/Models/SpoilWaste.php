<?php

namespace App\Modules\Operations\Models;

use App\Models\Scopes\TenantScope;
use App\Models\User;
use App\Modules\Core\Models\Department;
use App\Modules\Core\Models\Outlet;
use App\Modules\Inventory\Models\Item;
use App\Modules\Inventory\Models\Unit;
use App\Modules\Stock\Models\StockMutation;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SpoilWaste extends Model
{
    public const STATUS_PENDING = 'PENDING';
    public const STATUS_APPROVED = 'APPROVED';
    public const STATUS_REJECTED = 'REJECTED';

    public const REASON_EXPIRED = 'EXPIRED';
    public const REASON_RUSAK = 'RUSAK';
    public const REASON_KESALAHAN_PRODUKSI = 'KESALAHAN_PRODUKSI';
    public const REASON_TUMPAH = 'TUMPAH';
    public const REASON_QUALITY_REJECT = 'QUALITY_REJECT';
    public const REASON_LAINNYA = 'LAINNYA';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'outlet_id',
        'department_id',
        'item_id',
        'unit_id',
        'qty',
        'qty_in_base_unit',
        'reason_category',
        'reason_detail',
        'photo',
        'photo_path',
        'photo_hash',
        'perceptual_hash',
        'photo_meta',
        'is_duplicate_photo',
        'duplicate_ref_id',
        'recorded_date',
        'recorded_at',
        'status',
        'approval_status',
        'approved_by',
        'approved_at',
        'approval_notes',
        'mutation_id',
        'created_by',
        'device_info',
        'ip_address',
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
            'qty' => 'decimal:6',
            'qty_in_base_unit' => 'decimal:6',
            'photo_meta' => 'array',
            'is_duplicate_photo' => 'boolean',
            'recorded_date' => 'date',
            'recorded_at' => 'datetime',
            'approved_at' => 'datetime',
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

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function duplicateReference(): BelongsTo
    {
        return $this->belongsTo(self::class, 'duplicate_ref_id');
    }

    public function mutation(): BelongsTo
    {
        return $this->belongsTo(StockMutation::class, 'mutation_id');
    }

    protected function reasonLabel(): Attribute
    {
        return Attribute::get(fn (): string => [
            self::REASON_EXPIRED => 'Kadaluarsa',
            self::REASON_RUSAK => 'Rusak/Cacat',
            self::REASON_KESALAHAN_PRODUKSI => 'Kesalahan Produksi',
            self::REASON_TUMPAH => 'Tumpah',
            self::REASON_QUALITY_REJECT => 'Reject Kualitas',
            self::REASON_LAINNYA => 'Lainnya',
        ][$this->reason_category] ?? 'Lainnya');
    }

    protected function statusBadgeClass(): Attribute
    {
        return Attribute::get(fn (): string => [
            self::STATUS_PENDING => 'badge-pending',
            self::STATUS_APPROVED => 'badge-approved',
            self::STATUS_REJECTED => 'badge-rejected',
        ][$this->status] ?? 'badge-pending');
    }
}
