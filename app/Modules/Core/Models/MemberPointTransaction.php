<?php

namespace App\Modules\Core\Models;

use App\Modules\Pos\Models\PosOrder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class MemberPointTransaction extends Model
{
    use LogsActivity;

    public const TYPE_EARN = 'EARN';
    public const TYPE_REDEEM = 'REDEEM';

    protected static $recordEvents = ['created'];

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'points' => 'decimal:2',
            'balance_after' => 'decimal:2',
        ];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(PosOrder::class, 'pos_order_id');
    }

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new LogicException('Riwayat poin member bersifat permanen, tidak boleh diubah.');
        });

        static::deleting(function (): void {
            throw new LogicException('Riwayat poin member bersifat permanen, tidak boleh dihapus.');
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('pos')
            ->logOnly(['member_id', 'pos_order_id', 'type', 'points', 'balance_after']);
    }
}
