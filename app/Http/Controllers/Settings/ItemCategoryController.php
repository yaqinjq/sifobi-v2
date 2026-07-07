<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Modules\Inventory\Models\ItemCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ItemCategoryController extends Controller
{
    public function index(Request $request): View
    {
        $tenantId = $this->tenantId($request);

        return view('settings.item-categories.index', [
            'categories' => ItemCategory::query()
                ->where('tenant_id', $tenantId)
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $tenantId = $this->tenantId($request);
        $validated = $this->validated($request, $tenantId);

        DB::transaction(function () use ($tenantId, $validated): void {
            ItemCategory::withoutGlobalScopes()->create([
                'tenant_id' => $tenantId,
                'code' => strtoupper($validated['code']),
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'status' => 'ACTIVE',
                'is_active' => true,
                'sort_order' => (int) ($validated['sort_order'] ?? 0),
            ]);
        });

        return back()->with('success', 'Kategori bahan berhasil ditambahkan.');
    }

    public function update(Request $request, ItemCategory $itemCategory): RedirectResponse
    {
        $tenantId = $this->tenantId($request);
        $this->authorizeTenant($itemCategory, $tenantId);
        $validated = $this->validated($request, $tenantId, $itemCategory->id);

        DB::transaction(fn () => $itemCategory->update([
            'code' => strtoupper($validated['code']),
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'status' => 'ACTIVE',
            'is_active' => true,
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
        ]));

        return back()->with('success', 'Kategori bahan berhasil diperbarui.');
    }

    public function destroy(Request $request, ItemCategory $itemCategory): RedirectResponse
    {
        $tenantId = $this->tenantId($request);
        $this->authorizeTenant($itemCategory, $tenantId);

        DB::transaction(function () use ($itemCategory): void {
            if ($itemCategory->items()->exists()) {
                $itemCategory->update(['is_active' => false, 'status' => 'INACTIVE']);

                return;
            }

            $itemCategory->delete();
        });

        return back()->with('success', 'Kategori bahan berhasil dihapus dari pilihan aktif.');
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
                Rule::unique('item_categories', 'code')
                    ->where('tenant_id', $tenantId)
                    ->ignore($ignoreId),
            ],
            'name' => ['required', 'string', 'max:150'],
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

    private function authorizeTenant(ItemCategory $itemCategory, int $tenantId): void
    {
        abort_unless((int) $itemCategory->tenant_id === $tenantId, 404);
    }
}
