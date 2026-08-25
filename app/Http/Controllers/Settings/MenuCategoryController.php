<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Modules\Production\Models\MenuCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MenuCategoryController extends Controller
{
    public function index(Request $request): View
    {
        $tenantId = $this->tenantId($request);

        return view('settings.menu-categories.index', [
            'categories' => MenuCategory::query()
                ->where('tenant_id', $tenantId)
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
            'colors' => MenuCategory::COLORS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $tenantId = $this->tenantId($request);
        $validated = $this->validated($request, $tenantId);

        DB::transaction(function () use ($tenantId, $validated): void {
            MenuCategory::withoutGlobalScopes()->create(array_merge($validated, [
                'tenant_id' => $tenantId,
                'code' => strtoupper($validated['code']),
                'is_active' => true,
                'sort_order' => (int) ($validated['sort_order'] ?? 0),
            ]));
        });

        return back()->with('success', 'Kategori menu berhasil ditambahkan.');
    }

    public function update(Request $request, MenuCategory $menuCategory): RedirectResponse
    {
        $tenantId = $this->tenantId($request);
        $this->authorizeTenant($menuCategory, $tenantId);

        $validated = $this->validated($request, $tenantId, $menuCategory->id);

        DB::transaction(function () use ($menuCategory, $validated): void {
            $menuCategory->update(array_merge($validated, [
                'code' => strtoupper($validated['code']),
                'sort_order' => (int) ($validated['sort_order'] ?? 0),
            ]));
        });

        return back()->with('success', 'Kategori menu berhasil diperbarui.');
    }

    public function destroy(Request $request, MenuCategory $menuCategory): RedirectResponse
    {
        $tenantId = $this->tenantId($request);
        $this->authorizeTenant($menuCategory, $tenantId);

        DB::transaction(function () use ($menuCategory): void {
            if ($menuCategory->menus()->exists()) {
                $menuCategory->update(['is_active' => false]);

                return;
            }

            $menuCategory->delete();
        });

        return back()->with('success', 'Kategori menu berhasil dihapus dari pilihan aktif.');
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
                Rule::unique('menu_categories', 'code')
                    ->where('tenant_id', $tenantId)
                    ->ignore($ignoreId),
            ],
            'name' => ['required', 'string', 'max:150'],
            'color' => ['required', Rule::in(MenuCategory::COLORS)],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);
    }

    private function tenantId(Request $request): int
    {
        $tenantId = $request->user()?->tenant_id;

        abort_unless($tenantId, 403, 'Tenant belum terpasang pada user.');

        return (int) $tenantId;
    }

    private function authorizeTenant(MenuCategory $menuCategory, int $tenantId): void
    {
        abort_unless((int) $menuCategory->tenant_id === $tenantId, 404);
    }
}
