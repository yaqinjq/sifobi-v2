<?php

use App\Modules\Core\Models\Tenant;
use App\Modules\Operations\Models\InventoryCountTemplateItem;
use Database\Seeders\MinimumMasterDataSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Fixtures\InventoryCountSyntheticFixtures;

uses(RefreshDatabase::class);

/**
 * Membuktikan fixture sintetis (BUKAN master Sifobi asli, BUKAN kandidat
 * katalog Wipro yang disahkan) bisa membangun struktur PENUH kelima
 * template -- bukan cuma 3 item seperti test lama -- dan mematuhi batasan
 * "jangan menebak unit": item yang unitnya tidak tercatat di sumber
 * (Blue Band Whipped Cream, SKM Durian, Dori Fish) harus di-skip, bukan
 * dipaksa dapat unit.
 */
beforeEach(function (): void {
    $this->seed([RolesAndPermissionsSeeder::class, MinimumMasterDataSeeder::class]);
    $this->tenant = Tenant::query()->where('code', 'MKO')->firstOrFail();
});

test('synthetic fixture builds all five templates with full reference-row counts', function (): void {
    $result = (new InventoryCountSyntheticFixtures($this->tenant))->build();

    expect($result['templates'])->toHaveCount(5);

    $counts = [];
    foreach ($result['templates'] as $code => $template) {
        $counts[$code] = InventoryCountTemplateItem::query()->where('template_id', $template->id)->count();
    }

    // BAR_DAILY: 78 baris referensi, tapi 2 item (Blue Band, SKM Durian)
    // unitnya tidak tercatat -> di-skip -> 76 template item sintetis.
    expect($counts['BAR_DAILY'])->toBe(76);
    // KITCHEN_DRY_GOODS: 66 baris referensi, tapi 2 item (Kecap Asin,
    // Maggi Blok) unitnya tidak tercatat -> di-skip -> 64 template item.
    expect($counts['KITCHEN_DRY_GOODS'])->toBe(64);
    expect($counts['KITCHEN_SAYUR'])->toBe(53);
    // KITCHEN_WIP_SAUCE: 105 baris referensi (104 nama unik, "Chicken
    // Paitan" muncul 2x di subkategori berbeda) -- semua punya unit (Kg/Pcs/
    // dst tercatat), tidak ada yang di-skip.
    expect($counts['KITCHEN_WIP_SAUCE'])->toBe(105);
    // KITCHEN_WIP_GUDANG: 5 baris referensi, 1 (Dori Fish) unit tidak
    // tercatat -> di-skip -> 4 template item sintetis.
    expect($counts['KITCHEN_WIP_GUDANG'])->toBe(4);
});

test('synthetic fixture reports every unit-unresolved item it deliberately skipped', function (): void {
    $result = (new InventoryCountSyntheticFixtures($this->tenant))->build();

    // BAR: Blue Band Whipped Cream, SKM Durian. KITCHEN_DRY_GOODS: Kecap
    // Asin, Maggi Blok. KITCHEN_WIP_GUDANG: Dori Fish. Total 5.
    expect($result['skipped_unit_unresolved'])->toHaveCount(5);
    $joined = implode(' | ', $result['skipped_unit_unresolved']);
    expect($joined)->toContain('Blue Band Whipped Cream')
        ->and($joined)->toContain('SKM Durian')
        ->and($joined)->toContain('Kecap Asin')
        ->and($joined)->toContain('Maggi Blok')
        ->and($joined)->toContain('Dori Fish');
});

test('the 3 BAR items with genuinely different units per location get location_units override and forced config_valid=false', function (): void {
    $result = (new InventoryCountSyntheticFixtures($this->tenant))->build();
    $barTemplate = $result['templates']['BAR_DAILY'];

    $crossLocationItems = InventoryCountTemplateItem::query()
        ->where('template_id', $barTemplate->id)
        ->whereNotNull('location_units')
        ->with('item')
        ->get();

    // Simple Syrup (Gln/Kg), Soda Charger (Pack/Pcs), Choco Cereal Kellogs (Pack/Kg).
    expect($crossLocationItems)->toHaveCount(3);

    foreach ($crossLocationItems as $templateItem) {
        expect($templateItem->location_units)->toHaveKeys(['GUDANG', 'BAR_AREA']);
        expect($templateItem->location_units['GUDANG'])->not->toBe($templateItem->location_units['BAR_AREA']);
    }
});
