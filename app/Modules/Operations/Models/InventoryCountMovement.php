<?php

namespace App\Modules\Operations\Models;

use App\Models\Scopes\TenantScope;
use App\Models\User;
use App\Modules\Inventory\Models\Item;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu baris pergerakan IN/OUT harian (1 item x 1 tanggal) untuk fitur
 * Rekap Mingguan. TERISOLASI dari stock_balances/stock_mutations (ledger
 * resmi SIFOBI) -- lihat komentar migration. qty_in/qty_out NULL berarti
 * "belum diisi hari ini", BUKAN 0 (prinsip sama dengan
 * InventoryCountLine.is_counted).
 */
class InventoryCountMovement extends Model
{
    protected $table = 'inventory_count_movements';

    protected $fillable = [
        'tenant_id',
        'outlet_id',
        'department_id',
        'item_id',
        'movement_date',
        'qty_in',
        'qty_out',
        'recorded_by',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope);
    }

    protected function casts(): array
    {
        return [
            'movement_date' => 'date',
            'qty_in' => 'decimal:6',
            'qty_out' => 'decimal:6',
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
