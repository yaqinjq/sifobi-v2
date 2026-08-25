<?php

namespace App\Http\Controllers\Production;

use App\Http\Controllers\Controller;
use App\Modules\Core\Models\Brand;
use App\Modules\Production\Models\Menu;
use App\Modules\Production\Models\MenuCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MenuController extends Controller
{
    public function index(Request $request): View
    {
        $tenantId = $this->tenantId($request);

        $menus = Menu::query()
            ->where('tenant_id', $tenantId)
            ->with(['brand', 'category', 'recipes' => fn ($q) => $q->limit(1)])
            ->withCount('recipes')
            ->orderBy('name')
            ->paginate(20);

        return view('production.menus.index', compact('menus'));
    }

    public function create(Request $request): View
    {
        $tenantId = $this->tenantId($request);

        $brands = Brand::query()->where('tenant_id', $tenantId)->orderBy('name')->get();
        $categories = MenuCategory::query()->where('tenant_id', $tenantId)->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get();

        return view('production.menus.create', compact('brands', 'categories'));
    }

    public function store(Request $request): RedirectResponse
    {
        $tenantId = $this->tenantId($request);

        $validated = $request->validate([
            'brand_id'         => ['required', 'integer', Rule::exists('brands', 'id')->where('tenant_id', $tenantId)],
            'menu_category_id' => ['nullable', 'integer', Rule::exists('menu_categories', 'id')->where('tenant_id', $tenantId)],
            'code'             => ['nullable', 'string', 'max:32'],
            'name'             => ['required', 'string', 'max:150'],
            'selling_price'    => ['nullable', 'numeric', 'min:0'],
            'photo'            => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        $photoPath = $request->hasFile('photo')
            ? $request->file('photo')->store("tenants/{$tenantId}/menus", 'public')
            : null;

        $menu = Menu::query()->create([
            'tenant_id'         => $tenantId,
            'brand_id'          => $validated['brand_id'],
            'menu_category_id'  => $validated['menu_category_id'] ?? null,
            'code'              => $validated['code'] ?? null,
            'name'              => $validated['name'],
            'selling_price'     => $validated['selling_price'] ?? null,
            'photo_path'        => $photoPath,
            'is_active'         => true,
        ]);

        return redirect()
            ->route('production.menus.show', $menu)
            ->with('success', 'Menu berhasil dibuat. Sekarang buat resep pertamanya.');
    }

    public function show(Request $request, Menu $menu): View
    {
        $tenantId = $this->tenantId($request);

        abort_unless((int) $menu->tenant_id === $tenantId, 403);

        $menu->load([
            'brand',
            'category',
            'recipes.createdBy',
            'recipes.approvedBy',
            'recipes.outlets',
        ]);

        return view('production.menus.show', compact('menu'));
    }

    public function edit(Request $request, Menu $menu): View
    {
        $tenantId = $this->tenantId($request);

        abort_unless((int) $menu->tenant_id === $tenantId, 403);

        $brands = Brand::query()->where('tenant_id', $tenantId)->orderBy('name')->get();
        $categories = MenuCategory::query()->where('tenant_id', $tenantId)->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get();

        return view('production.menus.edit', compact('menu', 'brands', 'categories'));
    }

    public function update(Request $request, Menu $menu): RedirectResponse
    {
        $tenantId = $this->tenantId($request);

        abort_unless((int) $menu->tenant_id === $tenantId, 403);

        $validated = $request->validate([
            'brand_id'         => ['required', 'integer', Rule::exists('brands', 'id')->where('tenant_id', $tenantId)],
            'menu_category_id' => ['nullable', 'integer', Rule::exists('menu_categories', 'id')->where('tenant_id', $tenantId)],
            'code'             => ['nullable', 'string', 'max:32'],
            'name'             => ['required', 'string', 'max:150'],
            'selling_price'    => ['nullable', 'numeric', 'min:0'],
            'photo'            => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        if ($request->hasFile('photo')) {
            if ($menu->photo_path) {
                Storage::disk('public')->delete($menu->photo_path);
            }

            $validated['photo_path'] = $request->file('photo')->store("tenants/{$tenantId}/menus", 'public');
        }

        unset($validated['photo']);

        $menu->update($validated);

        return redirect()
            ->route('production.menus.show', $menu)
            ->with('success', 'Menu berhasil diperbarui.');
    }

    public function destroy(Request $request, Menu $menu): RedirectResponse
    {
        $tenantId = $this->tenantId($request);

        abort_unless((int) $menu->tenant_id === $tenantId, 403);

        if ($menu->canHardDelete()) {
            if ($menu->photo_path) {
                Storage::disk('public')->delete($menu->photo_path);
            }

            $menu->delete();

            return redirect()
                ->route('production.menus.index')
                ->with('success', 'Menu berhasil dihapus.');
        }

        $menu->update(['is_active' => false]);

        return redirect()
            ->route('production.menus.index')
            ->with('success', 'Menu dinonaktifkan (masih punya riwayat resep, tidak bisa dihapus permanen).');
    }

    private function tenantId(Request $request): int
    {
        $tenantId = $request->user()?->tenant_id;

        abort_unless($tenantId, 403, 'Tenant belum terpasang pada user.');

        return (int) $tenantId;
    }
}
