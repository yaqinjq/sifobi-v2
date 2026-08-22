<?php

namespace App\Modules\Pos\Models;

use App\Models\Scopes\TenantScope;
use App\Modules\Core\Models\Outlet;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class PosTable extends Model
{
    use LogsActivity;

    public const STATUS_AVAILABLE = 'AVAILABLE';
    public const STATUS_OCCUPIED  = 'OCCUPIED';
    public const STATUS_RESERVED  = 'RESERVED';
    public const STATUS_CLEANING  = 'CLEANING';

    public const STATUS_LABELS = [
        self::STATUS_AVAILABLE => 'Kosong',
        self::STATUS_OCCUPIED  => 'Terisi',
        self::STATUS_RESERVED  => 'Reserved',
        self::STATUS_CLEANING  => 'Dibersihkan',
    ];

    public const STATUS_BADGE_CLASS = [
        self::STATUS_AVAILABLE => 'bg-primary-100 text-primary-800 border-primary-300',
        self::STATUS_OCCUPIED  => 'bg-red-100 text-red-700 border-red-300',
        self::STATUS_RESERVED  => 'bg-amber-100 text-amber-800 border-amber-300',
        self::STATUS_CLEANING  => 'bg-gray-100 text-gray-600 border-gray-300',
    ];

    public const SHAPE_SQUARE = 'SQUARE';
    public const SHAPE_ROUND  = 'ROUND';
    public const SHAPE_RECT   = 'RECT';

    protected $fillable = [
        'tenant_id',
        'outlet_id',
        'pos_area_id',
        'code',
        'capacity',
        'pos_x',
        'pos_y',
        'shape',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'capacity' => 'integer',
            'pos_x' => 'integer',
            'pos_y' => 'integer',
        ];
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(PosArea::class, 'pos_area_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(PosOrder::class);
    }

    public function activeOrder(): ?PosOrder
    {
        return $this->orders()->where('status', PosOrder::STATUS_OPEN)->latest()->first();
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    public function statusBadgeClass(): string
    {
        return self::STATUS_BADGE_CLASS[$this->status] ?? self::STATUS_BADGE_CLASS[self::STATUS_AVAILABLE];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('pos')
            ->logFillable()
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope);
    }
}
