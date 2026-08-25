<?php

namespace App\Modules\Pos\Models;

use App\Models\Scopes\TenantScope;
use App\Models\User;
use App\Modules\Core\Models\Member;
use App\Modules\Core\Models\Outlet;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class PosOrder extends Model
{
    use LogsActivity;

    public const TYPE_DINE_IN  = 'DINE_IN';
    public const TYPE_TAKEAWAY = 'TAKEAWAY';

    public const STATUS_OPEN = 'OPEN';
    public const STATUS_PAID = 'PAID';
    public const STATUS_VOID = 'VOID';

    public const STATUS_LABELS = [
        self::STATUS_OPEN => 'Berjalan',
        self::STATUS_PAID => 'Lunas',
        self::STATUS_VOID => 'Dibatalkan',
    ];

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'subtotal'              => 'decimal:4',
            'discount_amount'       => 'decimal:4',
            'tax_amount'            => 'decimal:4',
            'service_charge_amount' => 'decimal:4',
            'total_amount'          => 'decimal:4',
            'opened_at'             => 'datetime',
            'closed_at'             => 'datetime',
        ];
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }

    public function table(): BelongsTo
    {
        return $this->belongsTo(PosTable::class, 'pos_table_id');
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PosOrderItem::class)->orderBy('sort_order');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(PosPayment::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function canAddItem(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }

    public function canCheckout(): bool
    {
        return $this->status === self::STATUS_OPEN && $this->items()->exists();
    }

    public function canVoid(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }

    public function canSplitOrMerge(): bool
    {
        return $this->status === self::STATUS_OPEN && $this->payments()->doesntExist();
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    public function statusBadgeClass(): string
    {
        return match ($this->status) {
            self::STATUS_OPEN => 'badge-pending',
            self::STATUS_PAID => 'badge-active',
            self::STATUS_VOID => 'badge-void',
            default => 'badge-draft',
        };
    }

    /**
     * Hitung ulang subtotal dari items yang sedang ter-load/dimuat, lalu
     * total_amount = subtotal - discount + tax + service_charge. Tidak
     * menyimpan (save()) sendiri — biar caller (PosOrderService) yang atur
     * transaksinya.
     */
    public function recalculateTotals(): void
    {
        $subtotal = $this->items()->sum('subtotal');

        $this->subtotal = $subtotal;
        $this->total_amount = bcadd(
            bcsub((string) $subtotal, (string) $this->discount_amount, 4),
            bcadd((string) $this->tax_amount, (string) $this->service_charge_amount, 4),
            4
        );
    }

    public function amountPaid(): string
    {
        return (string) $this->payments()->sum('amount');
    }

    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('pos')
            ->logOnly(['status', 'order_type', 'outlet_id', 'pos_table_id', 'total_amount'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
