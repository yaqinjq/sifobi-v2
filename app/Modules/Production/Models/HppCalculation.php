<?php

namespace App\Modules\Production\Models;

use App\Models\Scopes\TenantScope;
use App\Models\User;
use App\Modules\Core\Models\Outlet;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Kalkulator HPP coba-coba — TIDAK terhubung ke Menu/Recipe, tidak ada
 * approval. Untuk simulasi cepat sebelum resep dibuat resmi. Angka
 * dihitung & divalidasi di klien (Alpine), server cuma menyimpan snapshot-nya.
 */
class HppCalculation extends Model
{
    protected $fillable = [
        'tenant_id',
        'outlet_id',
        'created_by',
        'product_name',
        'payload_json',
        'ingredients_total',
        'other_costs_total',
        'total_cost',
        'volume_production',
        'hpp_per_unit',
    ];

    protected function casts(): array
    {
        return [
            'payload_json'       => 'array',
            'ingredients_total'  => 'decimal:4',
            'other_costs_total'  => 'decimal:4',
            'total_cost'         => 'decimal:4',
            'volume_production'  => 'decimal:4',
            'hpp_per_unit'       => 'decimal:4',
        ];
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope);
    }
}
