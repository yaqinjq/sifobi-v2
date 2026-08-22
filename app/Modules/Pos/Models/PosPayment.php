<?php

namespace App\Modules\Pos\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PosPayment extends Model
{
    public const METHOD_CASH    = 'CASH';
    public const METHOD_QRIS    = 'QRIS';
    public const METHOD_CARD    = 'CARD';
    public const METHOD_EWALLET = 'EWALLET';

    public const METHOD_LABELS = [
        self::METHOD_CASH    => 'Tunai',
        self::METHOD_QRIS    => 'QRIS',
        self::METHOD_CARD    => 'Kartu',
        self::METHOD_EWALLET => 'E-Wallet',
    ];

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'amount'  => 'decimal:4',
            'paid_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(PosOrder::class, 'pos_order_id');
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(PosShift::class, 'pos_shift_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function methodLabel(): string
    {
        return self::METHOD_LABELS[$this->method] ?? $this->method;
    }
}
