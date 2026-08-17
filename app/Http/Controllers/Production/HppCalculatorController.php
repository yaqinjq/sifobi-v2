<?php

namespace App\Http\Controllers\Production;

use App\Http\Controllers\Controller;
use App\Modules\Core\Models\Outlet;
use App\Modules\Production\Models\HppCalculation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class HppCalculatorController extends Controller
{
    public function index(Request $request): View
    {
        $tenantId = $this->tenantId($request);

        $history = HppCalculation::query()
            ->where('tenant_id', $tenantId)
            ->with('createdBy')
            ->latest()
            ->paginate(10);

        return view('production.hpp-calculator.index', [
            'history' => $history,
            'outlets' => Outlet::query()->where('tenant_id', $tenantId)->where('status', 'ACTIVE')->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $tenantId = $this->tenantId($request);

        $validated = $request->validate([
            'product_name'       => ['required', 'string', 'max:160'],
            'outlet_id'          => ['nullable', 'integer', Rule::exists('outlets', 'id')->where('tenant_id', $tenantId)],
            'volume_production'  => ['required', 'numeric', 'min:0.0001'],
            'ingredients_total'  => ['required', 'numeric', 'min:0'],
            'other_costs_total'  => ['required', 'numeric', 'min:0'],
            'total_cost'         => ['required', 'numeric', 'min:0'],
            'hpp_per_unit'       => ['required', 'numeric', 'min:0'],
            'ingredients'                => ['nullable', 'array'],
            'ingredients.*.name'         => ['required', 'string', 'max:150'],
            'ingredients.*.buy_qty'      => ['required', 'numeric', 'min:0'],
            'ingredients.*.buy_unit'     => ['required', 'string', 'max:20'],
            'ingredients.*.buy_price'    => ['required', 'numeric', 'min:0'],
            'ingredients.*.recipe_qty'   => ['required', 'numeric', 'min:0'],
            'ingredients.*.recipe_unit'  => ['required', 'string', 'max:20'],
            'ingredients.*.cost'         => ['required', 'numeric', 'min:0'],
            'other_costs'                => ['nullable', 'array'],
            'other_costs.*.cost_type'    => ['required', Rule::in(['PRODUCTION', 'OVERHEAD'])],
            'other_costs.*.label'        => ['required', 'string', 'max:150'],
            'other_costs.*.amount'       => ['required', 'numeric', 'min:0'],
        ]);

        HppCalculation::create([
            'tenant_id'         => $tenantId,
            'outlet_id'         => $validated['outlet_id'] ?? null,
            'created_by'        => $request->user()->id,
            'product_name'      => $validated['product_name'],
            'payload_json'      => [
                'ingredients'  => $validated['ingredients'] ?? [],
                'other_costs'  => $validated['other_costs'] ?? [],
            ],
            'ingredients_total' => $validated['ingredients_total'],
            'other_costs_total' => $validated['other_costs_total'],
            'total_cost'        => $validated['total_cost'],
            'volume_production' => $validated['volume_production'],
            'hpp_per_unit'      => $validated['hpp_per_unit'],
        ]);

        return redirect()
            ->route('production.hpp-calculator.index')
            ->with('success', 'Perhitungan HPP tersimpan di riwayat.');
    }

    public function show(Request $request, HppCalculation $hppCalculation): View
    {
        $tenantId = $this->tenantId($request);

        abort_unless((int) $hppCalculation->tenant_id === $tenantId, 403);

        return view('production.hpp-calculator.show', ['calc' => $hppCalculation]);
    }

    public function destroy(Request $request, HppCalculation $hppCalculation): RedirectResponse
    {
        $tenantId = $this->tenantId($request);

        abort_unless((int) $hppCalculation->tenant_id === $tenantId, 403);

        $hppCalculation->delete();

        return redirect()
            ->route('production.hpp-calculator.index')
            ->with('success', 'Riwayat perhitungan dihapus.');
    }

    private function tenantId(Request $request): int
    {
        $tenantId = $request->user()?->tenant_id;

        abort_unless($tenantId, 403, 'Tenant belum terpasang pada user.');

        return (int) $tenantId;
    }
}
