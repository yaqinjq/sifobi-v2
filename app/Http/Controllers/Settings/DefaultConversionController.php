<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Modules\Inventory\Models\Unit;
use App\Modules\Inventory\Models\UnitConversion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DefaultConversionController extends Controller
{
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

    private function tenantId(Request $request): int
    {
        $tenantId = $request->user()?->tenant_id;

        abort_unless($tenantId, 403, 'Tenant belum terpasang pada user.');

        return (int) $tenantId;
    }
}
