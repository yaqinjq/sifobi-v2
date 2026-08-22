<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Modules\Core\Models\Outlet;
use App\Modules\Pos\Models\PosArea;
use App\Modules\Pos\Models\PosOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PosAreaController extends Controller
{
    public function index(Request $request): View
    {
        $tenantId = $this->tenantId($request);
        $user = $request->user();

        $outlets = Outlet::query()->where('tenant_id', $tenantId)->orderBy('name')->get(['id', 'name', 'code']);

        $outletId = (int) ($request->integer('outlet_id') ?: ($user->outlet_id ?: $outlets->first()?->id));

        $outlet = $outlets->firstWhere('id', $outletId);

        $areas = $outlet
            ? PosArea::query()
                ->where('tenant_id', $tenantId)
                ->where('outlet_id', $outlet->id)
                ->with(['tables.orders' => fn ($q) => $q->where('status', PosOrder::STATUS_OPEN)])
                ->orderBy('sort_order')
                ->get()
            : collect();

        $unassignedTables = $outlet
            ? \App\Modules\Pos\Models\PosTable::query()
                ->where('tenant_id', $tenantId)
                ->where('outlet_id', $outlet->id)
                ->whereNull('pos_area_id')
                ->get()
            : collect();

        return view('pos.layout.index', compact('outlets', 'outlet', 'areas', 'unassignedTables'));
    }

    public function store(Request $request): RedirectResponse
    {
        $tenantId = $this->tenantId($request);

        $validated = $request->validate([
            'outlet_id'  => ['required', 'integer', Rule::exists('outlets', 'id')->where('tenant_id', $tenantId)],
            'name'       => ['required', 'string', 'max:100'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        PosArea::query()->create([
            'tenant_id'  => $tenantId,
            'outlet_id'  => $validated['outlet_id'],
            'name'       => $validated['name'],
            'sort_order' => $validated['sort_order'] ?? 0,
        ]);

        return redirect()
            ->route('pos.layout.index', ['outlet_id' => $validated['outlet_id']])
            ->with('success', 'Area berhasil ditambahkan.');
    }

    public function update(Request $request, PosArea $area): RedirectResponse
    {
        abort_unless((int) $area->tenant_id === $this->tenantId($request), 403);

        $validated = $request->validate([
            'name'       => ['required', 'string', 'max:100'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $area->update($validated);

        return redirect()
            ->route('pos.layout.index', ['outlet_id' => $area->outlet_id])
            ->with('success', 'Area berhasil diubah.');
    }

    public function destroy(Request $request, PosArea $area): RedirectResponse
    {
        abort_unless((int) $area->tenant_id === $this->tenantId($request), 403);

        $outletId = $area->outlet_id;

        $hasOpenOrder = PosOrder::query()
            ->whereHas('table', fn ($q) => $q->where('pos_area_id', $area->id))
            ->where('status', PosOrder::STATUS_OPEN)
            ->exists();

        if ($hasOpenOrder) {
            return back()->with('error', 'Ada order berjalan di area ini, tidak bisa dihapus.');
        }

        $area->tables()->update(['pos_area_id' => null]);
        $area->delete();

        return redirect()
            ->route('pos.layout.index', ['outlet_id' => $outletId])
            ->with('success', 'Area berhasil dihapus.');
    }

    private function tenantId(Request $request): int
    {
        $tenantId = $request->user()?->tenant_id;

        abort_unless($tenantId, 403, 'Tenant belum terpasang pada user.');

        return (int) $tenantId;
    }
}
