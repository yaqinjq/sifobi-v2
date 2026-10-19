<?php

namespace Tests\Fixtures;

use App\Modules\Core\Models\Department;
use App\Modules\Core\Models\Tenant;
use App\Modules\Inventory\Models\Item;
use App\Modules\Inventory\Models\Unit;
use App\Modules\Operations\Models\InventoryCountTemplate;
use App\Modules\Operations\Models\InventoryCountTemplateItem;

/**
 * SINTETIS, HANYA UNTUK DATABASE TEST. Membangun Unit/Item/Template/
 * TemplateItem dari nama+subkategori+unit SUMBER (Ah Pek) yang sudah
 * dibaca di docs/technical/inventory-simple-mapping-*.md -- TAPI item_id
 * yang dihasilkan di sini BUKAN item_id Sifobi asli dan BUKAN kandidat
 * katalog Wipro yang disahkan. canonical_sku selalu diawali "SYNTH-" dan
 * nama selalu diawali "[SYNTHETIC]" supaya tidak mungkin disalahartikan
 * sebagai master data nyata kalau fixture ini ter-dump ke mana pun.
 *
 * Dua item yang unitnya genuinely tidak tercatat di sumber manapun
 * (Blue Band Whipped Cream, SKM Durian di BAR; Dori Fish di WIP_GUDANG)
 * SENGAJA TIDAK dibuat jadi item sintetis -- membuat unit untuk item itu
 * berarti menebak, yang eksplisit dilarang.
 */
class InventoryCountSyntheticFixtures
{
    private const UNIT_MAP = [
        'KG' => ['Kilogram', 'kg', 3],
        'GR' => ['Gram', 'gr', 3],
        'ML' => ['Milliliter', 'ml', 3],
        'L' => ['Liter', 'l', 3],
        'PCS' => ['Pieces', 'pcs', 0],
        'PACK' => ['Pack', 'pack', 0],
        'DUS' => ['Dus', 'dus', 0],
        'CAN' => ['Can', 'can', 0],
        'PAIL' => ['Pail', 'pail', 0],
        'BTL' => ['Botol', 'btl', 0],
        'GLN' => ['Galon', 'gln', 3],
        'JAR' => ['Jar', 'jar', 0],
        'CRT' => ['Carton', 'crt', 0],
        'JER' => ['Jerigen', 'jer', 3],
        'RIM' => ['Rim', 'rim', 0],
        'ROLL' => ['Roll', 'roll', 0],
        'IKAT' => ['Ikat', 'ikat', 0],
        'SISIR' => ['Sisir', 'sisir', 0],
        'PORSI' => ['Porsi', 'porsi', 0],
    ];

    /** @var array<string,Unit> */
    private array $units = [];

    private int $seq = 0;

    public function __construct(private readonly Tenant $tenant)
    {
    }

    /**
     * @return array{templates: array<string, InventoryCountTemplate>, skipped_unit_unresolved: array<int, string>}
     */
    public function build(): array
    {
        $catalog = require __DIR__.'/inventory_count_synthetic_catalog.php';
        $templates = [];
        $skipped = [];

        foreach ($catalog as $code => $def) {
            $department = Department::query()
                ->where('tenant_id', $this->tenant->id)
                ->where('code', $def['department'])
                ->firstOrFail();

            $template = InventoryCountTemplate::query()->create([
                'tenant_id' => $this->tenant->id,
                'department_id' => $department->id,
                'code' => 'SYNTH_'.$code,
                'name' => '[SYNTHETIC] '.$code,
                'frequency' => 'DAILY',
                'locations' => $def['locations'],
                'is_generic' => false,
                'is_configured' => true,
            ]);

            $sortOrder = 0;
            foreach ($def['items'] as $row) {
                $sortOrder++;
                $isBar = $code === 'BAR_DAILY';

                $unitGudangCode = $isBar ? ($row['unit_gudang'] ?? null) : ($row['unit'] ?? null);
                $unitBarCode = $isBar ? ($row['unit_bar_area'] ?? null) : ($row['unit'] ?? null);

                if ($this->isUnresolved($unitGudangCode) || $this->isUnresolved($unitBarCode)) {
                    $skipped[] = "$code: {$row['name']} (unit sumber tidak tercatat, tidak dibuat fixture)";

                    continue;
                }

                $unitGudang = $this->resolveUnit($unitGudangCode);
                $unitBarArea = $this->resolveUnit($unitBarCode);

                // Item master sintetis dibuat dengan unit Gudang sebagai acuan
                // base/inventory unit -- kalau Bar Area beda (3 item yang
                // genuinely beda unit per lokasi), location_units override
                // dipasang supaya config_valid dipaksa false untuk lokasi itu
                // (lihat InventoryCountService::resolveLineConfig()).
                $item = $this->syntheticItem($code, $row['name'], $unitGudang);

                $locationUnits = null;
                if ($unitBarArea->id !== $unitGudang->id) {
                    $locationUnits = [
                        'GUDANG' => $unitGudang->id,
                        'BAR_AREA' => $unitBarArea->id,
                    ];
                }

                // "Onde - Onde" adalah kasus UNIT_UNRESOLVED nyata (lihat
                // inventory-simple-mapping-wip-master-crosscheck-2026-10-09.md
                // 3c): sumber Ah Pek bilang 1 Pack = 12 pcs, STANDARISASI WIP
                // bilang 1 Pack = 20 pcs. DEMI MEMBUKTIKAN jalur blokir submit
                // untuk rasio yang genuinely konflik -- BUKAN memilih salah
                // satu rasio -- item ini ditandai requires_conversion=true
                // TANPA rasio apa pun (item sintetis base_unit=inventory_unit
                // sama => hasValidRatio() otomatis false di
                // InventoryCountService::resolveLineConfig(), config_valid
                // jatuh false secara alami, tidak ada tebakan rasio).
                $isUnresolvedConversionDemo = str_contains(strtolower($row['name']), 'onde');

                InventoryCountTemplateItem::query()->create([
                    'tenant_id' => $this->tenant->id,
                    'template_id' => $template->id,
                    'item_id' => $item->id,
                    'subcategory_label' => $row['subcategory'],
                    'sort_order' => $sortOrder,
                    'locations' => $def['locations'],
                    'location_units' => $locationUnits,
                    'requires_conversion' => $isUnresolvedConversionDemo,
                    'is_active' => true,
                ]);
            }

            $templates[$code] = $template;
        }

        return ['templates' => $templates, 'skipped_unit_unresolved' => $skipped];
    }

    private function isUnresolved(?string $unitCode): bool
    {
        if ($unitCode === null) {
            return true;
        }

        $normalized = trim($unitCode);

        return $normalized === '' || $normalized === '-';
    }

    private function resolveUnit(?string $rawCode): Unit
    {
        $code = strtoupper(trim((string) $rawCode));
        if (! isset(self::UNIT_MAP[$code])) {
            $code = 'PCS'; // fallback eksplisit kalau kode unit tak dikenal kamus -- seharusnya tidak tercapai, isUnresolved() sudah menyaring kasus kosong/'-'.
        }

        if (isset($this->units[$code])) {
            return $this->units[$code];
        }

        [$name, $abbr, $decimals] = self::UNIT_MAP[$code] ?? ['Pieces', 'pcs', 0];

        $unit = Unit::query()->firstOrCreate(
            ['tenant_id' => $this->tenant->id, 'code' => $code],
            ['name' => $name, 'abbreviation' => $abbr, 'decimal_places' => $decimals, 'status' => 'ACTIVE']
        );

        $this->units[$code] = $unit;

        return $unit;
    }

    private function syntheticItem(string $templateCode, string $name, Unit $unit): Item
    {
        $this->seq++;
        $sku = 'SYNTH-'.$templateCode.'-'.str_pad((string) $this->seq, 3, '0', STR_PAD_LEFT);

        return Item::query()->create([
            'tenant_id' => $this->tenant->id,
            'canonical_sku' => $sku,
            'name' => '[SYNTHETIC] '.$name,
            'description' => 'Fixture sintetis untuk uji Inventory Simple -- bukan master item Sifobi asli.',
            'item_type' => 'BAHAN_BAKU',
            'inventory_unit_id' => $unit->id,
            'base_unit_id' => $unit->id,
            'purchase_unit_id' => $unit->id,
            'inventory_ratio' => '1.000000',
            'purchase_ratio' => '1.000000',
            'yield_pct' => '100.00',
            'standard_cost' => 0,
            'track_stock' => true,
            'is_active' => true,
            'item_source' => 'SYNTHETIC_TEST_FIXTURE',
        ]);
    }
}
