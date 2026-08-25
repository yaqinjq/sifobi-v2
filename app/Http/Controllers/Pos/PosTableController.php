<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Modules\Pos\Models\PosArea;
use App\Modules\Pos\Models\PosTable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PosTableController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $tenantId = $this->tenantId($request);

        $validated = $request->validate([
            'outlet_id'   => ['required', 'integer', Rule::exists('outlets', 'id')->where('tenant_id', $tenantId)],
            'pos_area_id' => ['nullable', 'integer', Rule::exists('pos_areas', 'id')->where('tenant_id', $tenantId)],
            'code'        => ['required', 'string', 'max:20', Rule::unique('pos_tables', 'code')->where('outlet_id', $request->integer('outlet_id'))],
            'capacity'    => ['nullable', 'integer', 'min:1'],
            'shape'       => ['required', Rule::in([PosTable::SHAPE_SQUARE, PosTable::SHAPE_ROUND, PosTable::SHAPE_RECT])],
        ]);

        PosTable::query()->create([
            'tenant_id'   => $tenantId,
            'outlet_id'   => $validated['outlet_id'],
            'pos_area_id' => $validated['pos_area_id'] ?? null,
            'code'        => $validated['code'],
            'capacity'    => $validated['capacity'] ?? null,
            'shape'       => $validated['shape'],
            'status'      => PosTable::STATUS_AVAILABLE,
            'pos_x'       => 20,
            'pos_y'       => 20,
        ]);

        return redirect()
            ->route('pos.layout.index', ['outlet_id' => $validated['outlet_id']])
            ->with('success', 'Meja berhasil ditambahkan.');
    }

    public function update(Request $request, PosTable $table): RedirectResponse
    {
        abort_unless((int) $table->tenant_id === $this->tenantId($request), 403);

        $validated = $request->validate([
            'pos_area_id' => ['nullable', 'integer', Rule::exists('pos_areas', 'id')->where('tenant_id', $table->tenant_id)],
            'code'        => ['required', 'string', 'max:20', Rule::unique('pos_tables', 'code')->where('outlet_id', $table->outlet_id)->ignore($table->id)],
            'capacity'    => ['nullable', 'integer', 'min:1'],
            'shape'       => ['required', Rule::in([PosTable::SHAPE_SQUARE, PosTable::SHAPE_ROUND, PosTable::SHAPE_RECT])],
        ]);

        $table->update($validated);

        return redirect()
            ->route('pos.layout.index', ['outlet_id' => $table->outlet_id])
            ->with('success', 'Meja berhasil diubah.');
    }

    public function destroy(Request $request, PosTable $table): RedirectResponse
    {
        abort_unless((int) $table->tenant_id === $this->tenantId($request), 403);

        $outletId = $table->outlet_id;

        if ($table->status !== PosTable::STATUS_AVAILABLE) {
            return back()->with('error', 'Meja sedang tidak kosong, tidak bisa dihapus.');
        }

        $table->delete();

        return redirect()
            ->route('pos.layout.index', ['outlet_id' => $outletId])
            ->with('success', 'Meja berhasil dihapus.');
    }

    public function updatePosition(Request $request, PosTable $table): JsonResponse
    {
        abort_unless((int) $table->tenant_id === $this->tenantId($request), 403);

        $validated = $request->validate([
            'pos_x' => ['required', 'integer'],
            'pos_y' => ['required', 'integer'],
        ]);

        $table->update($validated);

        return response()->json(['ok' => true]);
    }

    public function qr(Request $request, PosTable $table): View
    {
        abort_unless((int) $table->tenant_id === $this->tenantId($request), 403);

        $signedUrl = URL::signedRoute('public.pos.show', ['table' => $table->id]);

        return view('pos.tables.qr', compact('table', 'signedUrl'));
    }

    private function tenantId(Request $request): int
    {
        $tenantId = $request->user()?->tenant_id;

        abort_unless($tenantId, 403, 'Tenant belum terpasang pada user.');

        return (int) $tenantId;
    }
}
