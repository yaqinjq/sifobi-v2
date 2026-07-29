<?php

namespace App\Modules\Procurement\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrderApprovalEvent extends Model
{
    protected $guarded = [];

    const EVENT_SUBMIT  = 'SUBMIT';
    const EVENT_APPROVE = 'APPROVE';
    const EVENT_REJECT  = 'REJECT';
    const EVENT_SEND    = 'SEND';
    const EVENT_CLOSE   = 'CLOSE';
    const EVENT_REOPEN  = 'REOPEN';

    const EVENT_LABELS = [
        self::EVENT_SUBMIT  => 'Diajukan',
        self::EVENT_APPROVE => 'Disetujui',
        self::EVENT_REJECT  => 'Ditolak',
        self::EVENT_SEND    => 'Dikirim ke Vendor',
        self::EVENT_CLOSE   => 'Selesai',
        self::EVENT_REOPEN  => 'Dibuka kembali',
    ];

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function eventLabel(): string
    {
        return self::EVENT_LABELS[$this->event_type] ?? $this->event_type;
    }
}
