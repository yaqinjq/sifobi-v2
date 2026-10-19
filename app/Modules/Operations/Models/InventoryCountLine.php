<?php

namespace App\Modules\Operations\Models;

use App\Models\Scopes\TenantScope;
use App\Models\User;
use App\Modules\Inventory\Models\Item;
use App\Modules\Inventory\Models\Unit;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu baris observasi fisik (1 item x 1 lokasi) di dalam 1
 * InventoryCountDocument. Semua kolom unit/rasio di sini adalah SNAPSHOT
 * saat baris dibuat -- supaya dokumen lama tidak diam-diam berubah kalau
 * master item/template di-edit kemudian.
 */
class InventoryCountLine extends Model
{
    public const LOCATION_GUDANG = 'GUDANG';
    public const LOCATION_BAR_AREA = 'BAR_AREA';
    public const LOCATION_KITCHEN_AREA = 'KITCHEN_AREA';
    public const LOCATION_GENERIC = 'GENERIC';

    protected $table = 'inventory_count_lines';

    protected $fillable = [
        'tenant_id',
        'document_id',
        'item_id',
        'subcategory_label',
        'sort_order',
        'location',
        'qty_whole',
        'qty_loose',
        'whole_unit_id',
        'loose_unit_id',
        'conversion_factor',
        'requires_conversion',
        'config_valid',
        'is_counted',
        'counted_at',
        'counted_by',
        'notes',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope);
    }

    protected function casts(): array
    {
        return [
            'qty_whole' => 'decimal:6',
            'qty_loose' => 'decimal:6',
            'conversion_factor' => 'decimal:6',
            'requires_conversion' => 'boolean',
            'config_valid' => 'boolean',
            'is_counted' => 'boolean',
            'counted_at' => 'datetime',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(InventoryCountDocument::class, 'document_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function wholeUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'whole_unit_id');
    }

    public function looseUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'loose_unit_id');
    }

    public function countedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'counted_by');
    }

    /**
     * Total dalam base unit -- HANYA dihitung kalau konversi memang valid.
     * Sengaja mengembalikan null (bukan menganggap faktor 1) kalau
     * requires_conversion true tapi config_valid false -- jangan menampilkan
     * qty_base seolah sudah pasti padahal konfigurasi belum lengkap.
     */
    protected function qtyBase(): Attribute
    {
        return Attribute::get(function (): ?string {
            if (! $this->is_counted) {
                return null;
            }

            if (! $this->requires_conversion) {
                return $this->qty_whole !== null ? (string) $this->qty_whole : null;
            }

            if (! $this->config_valid || $this->conversion_factor === null) {
                return null;
            }

            $whole = (string) ($this->qty_whole ?? '0');
            $loose = (string) ($this->qty_loose ?? '0');

            return bcadd(bcmul($whole, (string) $this->conversion_factor, 6), $loose, 6);
        });
    }
}
