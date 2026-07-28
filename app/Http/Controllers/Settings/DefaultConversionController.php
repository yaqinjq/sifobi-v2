<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Modules\Inventory\Models\Unit;
use App\Modules\Inventory\Models\UnitConversion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DefaultConversionController extends Controller
{
    /**
     * Preset tangga konversi umum.
     * 'from' / 'to' adalah daftar kode unit yang dicoba (case-insensitive).
     * Pasangan dilewati jika salah satu unit tidak ditemukan di tenant.
     *
     * @var array<string, list<array{from: list<string>, to: list<string>, factor: string, label: string}>>
     */
    private const PRESETS = [
        'massa' => [
            ['from' => ['KG', 'KGR', 'KILO'], 'to' => ['GR', 'G', 'GRAM'],        'factor' => '1000.00000000', 'label' => 'KG → Gram'],
            ['from' => ['GR', 'G', 'GRAM'],    'to' => ['MG', 'MILIGRAM'],          'factor' => '1000.00000000', 'label' => 'Gram → mg'],
            ['from' => ['KG', 'KGR', 'KILO'],  'to' => ['MG', 'MILIGRAM'],          'factor' => '1000000.00000000', 'label' => 'KG → mg'],
            ['from' => ['TON'],                 'to' => ['KG', 'KGR', 'KILO'],      'factor' => '1000.00000000', 'label' => 'Ton → KG'],
            ['from' => ['TON'],                 'to' => ['GR', 'G', 'GRAM'],        'factor' => '1000000.00000000', 'label' => 'Ton → Gram'],
        ],
        'volume' => [
            ['from' => ['L', 'LTR', 'LITER'],  'to' => ['ML', 'CC', 'CM3'],        'factor' => '1000.00000000', 'label' => 'L → mL'],
            ['from' => ['L', 'LTR', 'LITER'],  'to' => ['DL', 'DESILITER'],        'factor' => '10.00000000',   'label' => 'L → dL'],
            ['from' => ['L', 'LTR', 'LITER'],  'to' => ['CL', 'CENTILITER'],       'factor' => '100.00000000',  'label' => 'L → cL'],
            ['from' => ['DL', 'DESILITER'],     'to' => ['ML', 'CC', 'CM3'],        'factor' => '100.00000000',  'label' => 'dL → mL'],
            ['from' => ['CL', 'CENTILITER'],    'to' => ['ML', 'CC', 'CM3'],        'factor' => '10.00000000',   'label' => 'cL → mL'],
        ],
    ];

    public function index(Request $request): View
    {
        $tenantId = $this->tenantId($request);

        $defaults = UnitConversion::query()
            ->where('tenant_id', $tenantId)
            ->whereNull('item_id')
            ->with(['fromUnit', 'toUnit'])
            ->orderBy('id')
            ->get();

        $units = Unit::query()
            ->where('tenant_id', $tenantId)
            ->where('status', 'ACTIVE')
            ->orderBy('name')
            ->get();

        return view('settings.default-conversions.index', compact('defaults', 'units'));
    }

    public function store(Request $request): RedirectResponse
    {
        $tenantId = $this->tenantId($request);

        $data = $request->validate([
            'from_unit_id' => ['required', 'integer', 'exists:units,id'],
            'to_unit_id'   => ['required', 'integer', 'exists:units,id', 'different:from_unit_id'],
            'factor'       => ['required', 'numeric', 'min:0.00000001'],
        ]);

        $fromUnit = Unit::withoutGlobalScopes()->find($data['from_unit_id']);
        $toUnit   = Unit::withoutGlobalScopes()->find($data['to_unit_id']);

        abort_unless($fromUnit && (int) $fromUnit->tenant_id === $tenantId, 422, 'Satuan tidak valid.');
        abort_unless($toUnit && (int) $toUnit->tenant_id === $tenantId, 422, 'Satuan tidak valid.');

        $factor = number_format((float) $data['factor'], 8, '.', '');

        DB::transaction(function () use ($tenantId, $data, $factor): void {
            UnitConversion::withoutGlobalScopes()->updateOrCreate(
                [
                    'tenant_id'    => $tenantId,
                    'item_id'      => null,
                    'from_unit_id' => $data['from_unit_id'],
                    'to_unit_id'   => $data['to_unit_id'],
                ],
                [
                    'multiply_rate' => $factor,
                    'factor'        => $factor,
                ]
            );
        });

        return back()->with('success', 'Konversi default berhasil disimpan.');
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        $tenantId = $this->tenantId($request);

        $conversion = UnitConversion::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereNull('item_id')
            ->findOrFail($id);

        DB::transaction(fn () => $conversion->delete());

        return back()->with('success', 'Konversi default berhasil dihapus.');
    }

    public function loadPreset(Request $request, string $type): RedirectResponse
    {
        abort_unless(array_key_exists($type, self::PRESETS), 404);

        $tenantId = $this->tenantId($request);

        $units = Unit::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('status', 'ACTIVE')
            ->get()
            ->keyBy(fn (Unit $u): string => strtoupper(trim($u->code)));

        $applied  = 0;
        $skipped  = [];

        DB::transaction(function () use ($type, $units, $tenantId, &$applied, &$skipped): void {
            foreach (self::PRESETS[$type] as $rule) {
                $fromUnit = $this->findUnit($units, $rule['from']);
                $toUnit   = $this->findUnit($units, $rule['to']);

                if (! $fromUnit || ! $toUnit) {
                    $skipped[] = $rule['label'];
                    continue;
                }

                UnitConversion::withoutGlobalScopes()->updateOrCreate(
                    [
                        'tenant_id'    => $tenantId,
                        'item_id'      => null,
                        'from_unit_id' => $fromUnit->id,
                        'to_unit_id'   => $toUnit->id,
                    ],
                    [
                        'multiply_rate' => $rule['factor'],
                        'factor'        => $rule['factor'],
                    ]
                );

                $applied++;
            }
        });

        $label = $type === 'massa' ? 'Tangga Massa' : 'Tangga Volume';

        if ($applied === 0) {
            return back()->with('warning', "Preset {$label}: tidak ada satuan yang cocok ditemukan. Pastikan kode satuan sudah sesuai (KG, GR, L, ML, dll).");
        }

        $msg = "Preset {$label}: {$applied} konversi berhasil diterapkan.";

        if (! empty($skipped)) {
            $msg .= ' Dilewati (' . implode(', ', $skipped) . '): satuan tidak ditemukan.';
        }

        return back()->with('success', $msg);
    }

    /**
     * @param  Collection<string, Unit>  $units
     * @param  list<string>  $codes
     */
    private function findUnit(Collection $units, array $codes): ?Unit
    {
        foreach ($codes as $code) {
            $unit = $units->get(strtoupper($code));
            if ($unit) {
                return $unit;
            }
        }

        return null;
    }

    private function tenantId(Request $request): int
    {
        $tenantId = $request->user()?->tenant_id;

        abort_unless($tenantId, 403, 'Tenant belum terpasang pada user.');

        return (int) $tenantId;
    }
}
