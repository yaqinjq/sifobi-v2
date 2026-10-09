<?php

namespace App\Modules\Operations\Models;

use App\Models\Scopes\TenantScope;
use App\Models\User;
use App\Modules\Inventory\Models\Item;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Saldo awal minggu (per item per scope) -- titik mulai perhitungan
 * saldo berjalan Rekap Mingguan. Lihat
 * InventoryCountMovementService::calculateBalance().
 */
class InventoryCountOpeningBalance extends Model
{
    protected $table = 'inventory_count_opening_balances';

    protected $fillable = [
        'tenant_id',
        'outlet_id',
        'department_id',
        'item_id',
        'week_start_date',
        'qty',
        'recorded_by',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope);
    }

    protected function casts(): array
    {
        return [
            'week_start_date' => 'date',
            'qty' => 'decimal:6',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
