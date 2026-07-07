<?php

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\Controller;
use App\Modules\Inventory\Models\Unit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UnitController extends Controller
{
    public function index(Request $request): View
    {
        $tenantId = $this->tenantId($request);

        $units = Unit::query()
            ->where('tenant_id', $tenantId)
            ->withCount([
                'baseItems',
                'inventoryItems',
                'purchaseItems',
                'conversionsFrom',
                'conversionsTo',
            ])
            ->orderBy('code')
            ->get();

        return view('master-data.units.index', [
            'units' => $units,
        ]);
    }

    public function create(): View
    {
        return view('master-data.units.create', [
            'unit' => new Unit(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $tenantId = $this->tenantId($request);
        $validated = $this->validated($request, $tenantId);

        Unit::query()->create([
            'tenant_id' => $tenantId,
            'code' => strtoupper($validated['code']),
            'name' => $validated['name'],
            'abbreviation' => $validated['abbreviation'],
            'status' => 'ACTIVE',
        ]);

        return redirect()
            ->route('master-data.units.index')
            ->with('success', 'Satuan berhasil ditambahkan.');
    }

    public function edit(Request $request, Unit $unit): View
    {
        $this->authorizeTenant($request, $unit);

        return view('master-data.units.edit', [
            'unit' => $unit,
        ]);
    }

    public function update(Request $request, Unit $unit): RedirectResponse
    {
        $tenantId = $this->tenantId($request);
        $this->authorizeTenant($request, $unit);
        $validated = $this->validated($request, $tenantId, $unit);

        $unit->update([
            'code' => strtoupper($validated['code']),
            'name' => $validated['name'],
            'abbreviation' => $validated['abbreviation'],
        ]);

        return redirect()
            ->route('master-data.units.index')
            ->with('success', 'Satuan berhasil diperbarui.');
    }

    public function destroy(Request $request, Unit $unit): RedirectResponse
    {
        $this->authorizeTenant($request, $unit);

        $unit->loadCount([
            'baseItems',
            'inventoryItems',
            'purchaseItems',
            'conversionsFrom',
            'conversionsTo',
        ]);

        $usageCount = $unit->base_items_count
            + $unit->inventory_items_count
            + $unit->purchase_items_count
            + $unit->conversions_from_count
            + $unit->conversions_to_count;

        if ($usageCount > 0) {
            return back()->with('error', 'Satuan tidak bisa dihapus karena sudah dipakai di item atau konversi.');
        }

        DB::transaction(fn () => $unit->delete());

        return redirect()
            ->route('master-data.units.index')
            ->with('success', 'Satuan berhasil dihapus.');
    }

    /**
     * @return array{name:string,code:string,abbreviation:string}
     */
    private function validated(Request $request, int $tenantId, ?Unit $unit = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'code' => [
                'required',
                'string',
                'max:20',
                Rule::unique('units', 'code')
                    ->where('tenant_id', $tenantId)
                    ->ignore($unit?->id),
            ],
            'abbreviation' => ['required', 'string', 'max:20'],
        ]);
    }

    private function tenantId(Request $request): int
    {
        $tenantId = $request->user()?->tenant_id;

        abort_unless($tenantId, 403, 'Tenant belum terpasang pada user.');

        return (int) $tenantId;
    }

    private function authorizeTenant(Request $request, Unit $unit): void
    {
        abort_unless($unit->tenant_id === $this->tenantId($request), 404);
    }
}
