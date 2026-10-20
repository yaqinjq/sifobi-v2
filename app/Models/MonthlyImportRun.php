<?php

namespace App\Models;

use App\Models\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Model;

class MonthlyImportRun extends Model
{
    public const TYPE_OPNAME = 'OPNAME';

    public const TYPE_SPOIL = 'SPOIL';

    public const STATUS_QUEUED = 'QUEUED';

    public const STATUS_PROCESSING = 'PROCESSING';

    public const STATUS_DONE = 'DONE';

    public const STATUS_FAILED = 'FAILED';

    protected $fillable = [
        'tenant_id',
        'type',
        'scope',
        'status',
        'total_units',
        'processed_units',
        'failed_units',
        'errors',
        'upload_path',
        'created_by',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'scope' => 'array',
            'errors' => 'array',
            'finished_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope);
    }

    public function createdBy(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
