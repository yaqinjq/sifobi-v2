<?php

namespace App\Modules\Operations\Models;

use App\Models\User;
use App\Modules\Core\Models\Department;
use App\Modules\Core\Models\Outlet;
use App\Modules\Inventory\Models\Item;
use App\Modules\Inventory\Models\Unit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OpenStock extends Model
{
    public const STATUS_DRAFT  = 'DRAFT';
    public const STATUS_POSTED = 'POSTED';
    public const STATUS_VOID   = 'VOID';

    public const TARGET_OUTLET_DAILY     = 'OUTLET_DAILY';
    public const TARGET_OUTLET_WAREHOUSE = 'OUTLET_WAREHOUSE';

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    public static function targetOptions(): array
    {
        return [
            self::TARGET_OUTLET_DAILY     => 'Stok Harian Outlet',
            self::TARGET_OUTLET_WAREHOUSE => 'Gudang Outlet',
        ];
    }

    public function targetLabel(): string
    {
        return self::targetOptions()[$this->stock_target] ?? $this->stock_target;
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

    public function postedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    public function voidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    protected function casts(): array
    {
        return [
            'business_date'   => 'date',
            'qty_whole'       => 'decimal:6',
            'qty_loose'       => 'decimal:6',
            'qty_in_base_unit'=> 'decimal:6',
            'cost_per_unit'   => 'decimal:4',
            'posted_at'       => 'datetime',
            'voided_at'       => 'datetime',
        ];
    }
}
