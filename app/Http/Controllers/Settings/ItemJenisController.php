<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Modules\Inventory\Models\ItemJenis;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ItemJenisController extends Controller
{
    public function index(Request $request): View
    {
        $tenantId = $this->tenantId($request);

        return view('settings.item-jenises.index', [
            'jenises' => ItemJenis::query()
                ->where('tenant_id', $tenantId)
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
            'colors' => ItemJenis::COLORS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $tenantId = $this->tenantId($request);
        $validated = $this->validated($request, $tenantId);

        DB::transaction(function () use ($tenantId, $validated): void {
            ItemJenis::withoutGlobalScopes()->create(array_merge($validated, [
                'tenant_id' => $tenantId,
                'code' => strtoupper($validated['code']),
                'is_active' => true,
                'sort_order' => (int) ($validated['sort_order'] ?? 0),
            ]));
        });

        return back()->with('success', 'Jenis bahan berhasil ditambahkan.');
    }

    public function update(Request $request, ItemJenis $itemJenis): RedirectResponse
    {
        $tenantId = $this->tenantId($request);
        $this->authorizeTenant($itemJenis, $tenantId);

        $validated = $this->validated($request, $tenantId, $itemJenis->id);

        DB::transaction(function () use ($itemJenis, $validated): void {
            $itemJenis->update(array_merge($validated, [
                'code' => strtoupper($validated['code']),
                'sort_order' => (int) ($validated['sort_order'] ?? 0),
            ]));
        });

        return back()->with('success', 'Jenis bahan berhasil diperbarui.');
    }

    public function destroy(Request $request, ItemJenis $itemJenis): RedirectResponse
    {
        $tenantId = $this->tenantId($request);
        $this->authorizeTenant($itemJenis, $tenantId);

        DB::transaction(function () use ($itemJenis): void {
            if ($itemJenis->items()->exists()) {
                $itemJenis->update(['is_active' => false]);

                return;
            }

            $itemJenis->delete();
        });

        return back()->with('success', 'Jenis bahan berhasil dihapus dari pilihan aktif.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, int $tenantId, ?int $ignoreId = null): array
    {
        return $request->validate([
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('item_jenises', 'code')
                    ->where('tenant_id', $tenantId)
                    ->ignore($ignoreId),
            ],
            'name' => ['required', 'string', 'max:150'],
            'color' => ['required', Rule::in(ItemJenis::COLORS)],
            'description' => ['nullable', 'string', 'max:2000'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);
    }

    private function tenantId(Request $request): int
    {
        $tenantId = $request->user()?->tenant_id;

        abort_unless($tenantId, 403, 'Tenant belum terpasang pada user.');

        return (int) $tenantId;
    }

    private function authorizeTenant(ItemJenis $itemJenis, int $tenantId): void
    {
        abort_unless((int) $itemJenis->tenant_id === $tenantId, 404);
    }
}
