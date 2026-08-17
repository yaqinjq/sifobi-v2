<?php

namespace App\Modules\Production\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecipeApprovalEvent extends Model
{
    protected $guarded = [];

    const EVENT_SUBMIT  = 'SUBMIT';
    const EVENT_APPROVE = 'APPROVE';
    const EVENT_REJECT  = 'REJECT';
    const EVENT_REOPEN  = 'REOPEN';

    const EVENT_LABELS = [
        self::EVENT_SUBMIT  => 'Diajukan',
        self::EVENT_APPROVE => 'Disetujui',
        self::EVENT_REJECT  => 'Ditolak',
        self::EVENT_REOPEN  => 'Dibuka kembali',
    ];

    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class);
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
