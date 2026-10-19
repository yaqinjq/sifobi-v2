<?php

namespace App\Modules\Operations\Models;

use App\Models\Scopes\TenantScope;
use App\Modules\Inventory\Models\Item;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu baris konfigurasi item di dalam 1 InventoryCountTemplate -- item_id
 * WAJIB merujuk ke master Item existing (bukan pencocokan nama), supaya
 * konsisten dengan seluruh sistem SIFOBI dan tidak rapuh seperti
 * carry-forward by-nama di form kertas Ah Pek asli.
 */
class InventoryCountTemplateItem extends Model
{
    protected $table = 'inventory_count_template_items';

    protected $fillable = [
        'tenant_id',
        'template_id',
        'item_id',
        'subcategory_label',
        'sort_order',
        'locations',
        'location_units',
        'requires_conversion',
        'is_active',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope);
    }

    protected function casts(): array
    {
        return [
            'locations' => 'array',
            'location_units' => 'array',
            'requires_conversion' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(InventoryCountTemplate::class, 'template_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    /**
     * @return array<string, int> lokasi => unit override yang TIDAK cocok
     *         dengan unit dasar item (mis. Simple Syrup: Gln di Gudang vs
     *         Kg di Bar Area -- lihat temuan UAT 2026-10-13 Section I.3).
     */
    public function locationUnitMismatches(Item $item): array
    {
        $masterUnitId = $item->masterUnitId();
        $mismatches = [];

        foreach ((array) ($this->location_units ?? []) as $location => $overrideUnitId) {
            if ($overrideUnitId === null) {
                continue;
            }

            $matches = $masterUnitId !== null && (int) $overrideUnitId === (int) $masterUnitId;
            if (! $matches) {
                $mismatches[$location] = (int) $overrideUnitId;
            }
        }

        return $mismatches;
    }

    /**
     * Dipakai badge/banner "Perlu Perhatian" di Master Data > Items --
     * SATU sumber kebenaran yang sama dengan
     * InventoryCountService::resolveLineConfig() (lihat Item::hasValidInventoryRatio()).
     *
     * @return list<array{type: string, template_id: int, template_name: ?string, message: string, locations?: array<string, int>}>
     */
    public function configIssues(Item $item): array
    {
        $issues = [];

        if ($this->requires_conversion && ! $item->hasValidInventoryRatio()) {
            $issues[] = [
                'type' => 'ratio_unresolved',
                'template_id' => $this->template_id,
                'template_name' => $this->template?->name,
                'message' => 'Rasio konversi Utuh/Ecer belum dikonfigurasi untuk item ini.',
            ];
        }

        $mismatches = $this->locationUnitMismatches($item);
        if ($mismatches !== []) {
            $issues[] = [
                'type' => 'location_unit_mismatch',
                'template_id' => $this->template_id,
                'template_name' => $this->template?->name,
                'message' => 'Satuan override per lokasi tidak sama dengan satuan dasar item.',
                'locations' => $mismatches,
            ];
        }

        return $issues;
    }
}
