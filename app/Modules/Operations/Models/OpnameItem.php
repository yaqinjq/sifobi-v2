<?php

namespace App\Modules\Operations\Models;

use App\Modules\Core\Models\Department;
use App\Modules\Inventory\Models\Item;
use App\Modules\Inventory\Models\Unit;
use App\Modules\Stock\Models\StockMutation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OpnameItem extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'opname_session_id',
        'item_id',
        'unit_id',
        'department_id',
        'system_qty',
        'system_qty_base',
        'counted_qty',
        'physical_qty_whole',
        'physical_qty_loose',
        'physical_qty_base',
        'variance_qty',
        'variance',
        'variance_value',
        'is_counted',
        'mutation_id',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'system_qty' => 'decimal:6',
            'system_qty_base' => 'decimal:6',
            'counted_qty' => 'decimal:6',
            'physical_qty_whole' => 'decimal:6',
            'physical_qty_loose' => 'decimal:6',
            'physical_qty_base' => 'decimal:6',
            'variance_qty' => 'decimal:6',
            'variance' => 'decimal:6',
            'variance_value' => 'decimal:4',
            'is_counted' => 'boolean',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(OpnameSession::class, 'opname_session_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function mutation(): BelongsTo
    {
        return $this->belongsTo(StockMutation::class, 'mutation_id');
    }
}
