<?php

namespace App\Http\Controllers\Production;

use App\Http\Controllers\Controller;
use App\Modules\Core\Models\Brand;
use App\Modules\Production\Models\Menu;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MenuController extends Controller
{
    public function index(Request $request): View
    {
        $tenantId = $this->tenantId($request);

        $menus = Menu::query()
            ->where('tenant_id', $tenantId)
            ->with(['brand', 'recipes' => fn ($q) => $q->limit(1)])
            ->withCount('recipes')
            ->orderBy('name')
            ->paginate(20);

        return view('production.menus.index', compact('menus'));
    }

    public function create(Request $request): View
    {
        $tenantId = $this->tenantId($request);

        $brands = Brand::query()->where('tenant_id', $tenantId)->orderBy('name')->get();

        return view('production.menus.create', compact('brands'));
    }

    public function store(Request $request): RedirectResponse
    {
        $tenantId = $this->tenantId($request);

        $validated = $request->validate([
            'brand_id' => ['required', 'integer', Rule::exists('brands', 'id')->where('tenant_id', $tenantId)],
            'code'     => ['nullable', 'string', 'max:32'],
            'name'     => ['required', 'string', 'max:150'],
        ]);

        $menu = Menu::query()->create([
            'tenant_id' => $tenantId,
            'brand_id'  => $validated['brand_id'],
            'code'      => $validated['code'] ?? null,
            'name'      => $validated['name'],
            'is_active' => true,
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
            'recipes.createdBy',
            'recipes.approvedBy',
            'recipes.outlets',
        ]);

        return view('production.menus.show', compact('menu'));
    }

    private function tenantId(Request $request): int
    {
        $tenantId = $request->user()?->tenant_id;

        abort_unless($tenantId, 403, 'Tenant belum terpasang pada user.');

        return (int) $tenantId;
    }
}
