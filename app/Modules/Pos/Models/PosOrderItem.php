<?php

namespace App\Modules\Pos\Models;

use App\Models\User;
use App\Modules\Production\Models\Menu;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PosOrderItem extends Model
{
    public const STATUS_PENDING = 'PENDING';
    public const STATUS_READY = 'READY';

    public const STATUS_LABELS = [
        self::STATUS_PENDING => 'Diproses',
        self::STATUS_READY => 'Siap',
    ];

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:4',
            'qty'        => 'decimal:4',
            'subtotal'   => 'decimal:4',
            'sort_order' => 'integer',
            'prepared_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(PosOrder::class, 'pos_order_id');
    }

    public function menu(): BelongsTo
    {
        return $this->belongsTo(Menu::class);
    }

    public function preparedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prepared_by');
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    public function statusBadgeClass(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'badge-pending',
            self::STATUS_READY => 'badge-active',
            default => 'badge-draft',
        };
    }
}
