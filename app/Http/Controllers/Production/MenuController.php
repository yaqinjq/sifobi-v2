<?php

namespace App\Http\Controllers\Production;

use App\Http\Controllers\Concerns\HasBulkAction;
use App\Http\Controllers\Concerns\HasPerPageSelector;
use App\Http\Controllers\Concerns\ResolvesTableSort;
use App\Http\Controllers\Controller;
use App\Http\Requests\Production\BulkDestroyMenuRequest;
use App\Imports\MenuImport;
use App\Modules\Core\Models\Brand;
use App\Modules\Production\Models\Menu;
use App\Modules\Production\Models\MenuCategory;
use App\Modules\Production\Models\Recipe;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;

class MenuController extends Controller
{
    use HasBulkAction;
    use HasPerPageSelector;
    use ResolvesTableSort;

    /** Filter "Status Resep" di daftar menu. */
    public const RECIPE_FILTERS = [
        'approved' => 'Sudah disetujui',
        'pending'  => 'Menunggu persetujuan',
        'draft'    => 'Masih draft / ditolak',
        'none'     => 'Belum ada resep',
    ];

    public function index(Request $request): View
    {
        $tenantId = $this->tenantId($request);
        [$perPage, $perPageOptions] = $this->perPageAndOptions($request, 20);
        [$sort, $direction] = $this->resolveTableSort($request, ['name', 'code', 'selling_price'], 'name');

        $filters = [
            'q'         => trim((string) $request->string('q')),
            'brand_id'  => $request->integer('brand_id') ?: null,
            'category'  => $request->string('category')->toString(),
            'recipe'    => array_key_exists($request->string('recipe')->toString(), self::RECIPE_FILTERS) ? $request->string('recipe')->toString() : '',
            'status'    => in_array($request->string('status')->toString(), ['active', 'inactive', 'all'], true) ? $request->string('status')->toString() : 'active',
        ];

        // Semua filter KECUALI kategori -- dipakai juga utk hitungan chip
        // kategori supaya angkanya ikut menyesuaikan pencarian/brand/status.
        $base = Menu::query()
            ->where('tenant_id', $tenantId)
            ->when($filters['q'] !== '', fn ($q) => $q->where(fn ($inner) => $inner
                ->where('name', 'like', "%{$filters['q']}%")
                ->orWhere('code', 'like', "%{$filters['q']}%")))
            ->when($filters['brand_id'], fn ($q, $brandId) => $q->where('brand_id', $brandId))
            ->when($filters['status'] === 'active', fn ($q) => $q->where('is_active', true))
            ->when($filters['status'] === 'inactive', fn ($q) => $q->where('is_active', false))
            ->when($filters['recipe'] === 'approved', fn ($q) => $q->whereHas('recipes', fn ($r) => $r->where('status', Recipe::STATUS_APPROVED)))
            ->when($filters['recipe'] === 'pending', fn ($q) => $q->whereHas('recipes', fn ($r) => $r->where('status', Recipe::STATUS_SUBMITTED)))
            ->when($filters['recipe'] === 'draft', fn ($q) => $q->has('recipes')
                ->whereDoesntHave('recipes', fn ($r) => $r->whereIn('status', [Recipe::STATUS_APPROVED, Recipe::STATUS_SUBMITTED])))
            ->when($filters['recipe'] === 'none', fn ($q) => $q->doesntHave('recipes'));

        $categoryCounts = (clone $base)
            ->selectRaw('menu_category_id, COUNT(*) as total')
            ->groupBy('menu_category_id')
            ->pluck('total', 'menu_category_id');

        $menus = (clone $base)
            ->when($filters['category'] === 'none', fn ($q) => $q->whereNull('menu_category_id'))
            ->when(ctype_digit($filters['category']), fn ($q) => $q->where('menu_category_id', (int) $filters['category']))
            ->with([
                'brand',
                'category',
                'recipes' => fn ($q) => $q->select(['id', 'menu_id', 'version_number', 'status', 'volume_production']),
                'approvedRecipes.ingredients.item',
                'approvedRecipes.ingredients.buyUnit',
                'approvedRecipes.ingredients.recipeUnit',
                'approvedRecipes.otherCosts',
            ])
            ->orderBy($sort, $direction)
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();

        // HPP dari resep DISETUJUI versi terbaru (approvedRecipes sudah urut
        // version_number desc). Dihitung per halaman saja, bukan semua menu.
        $hppByMenu = $menus->getCollection()->mapWithKeys(function (Menu $menu): array {
            $recipe = $menu->approvedRecipes->first();

            return [$menu->id => $recipe ? $recipe->hpp()['hpp_per_unit'] : null];
        });

        $categories = MenuCategory::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('id', $categoryCounts->keys()->filter())
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name']);

        $brands = Brand::query()
            ->where('tenant_id', $tenantId)
            ->whereHas('menus')
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('production.menus.index', [
            'menus'          => $menus,
            'perPage'        => $perPage,
            'perPageOptions' => $perPageOptions,
            'sort'           => $sort,
            'direction'      => $direction,
            'filters'        => $filters,
            'categories'     => $categories,
            'categoryCounts' => $categoryCounts,
            'totalCount'     => $categoryCounts->sum(),
            'brands'         => $brands,
            'hppByMenu'      => $hppByMenu,
            'recipeFilters'  => self::RECIPE_FILTERS,
        ]);
    }

    public function create(Request $request): View
    {
        $tenantId = $this->tenantId($request);

        $brands = Brand::query()->where('tenant_id', $tenantId)->orderBy('name')->get();
        $categories = MenuCategory::query()->where('tenant_id', $tenantId)->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get();

        return view('production.menus.create', compact('brands', 'categories'));
    }

    public function importForm(Request $request): View
    {
        $tenantId = $this->tenantId($request);

        // Jumlah outlet per brand ditampilkan supaya user memilih brand yang
        // SAMA dengan brand outlet-outletnya -- pencarian spoil menu
        // difilter per brand outlet (SpoilWasteController::searchableMenus()).
        $brands = Brand::query()
            ->where('tenant_id', $tenantId)
            ->where('status', 'ACTIVE')
            ->withCount('outlets')
            ->orderBy('name')
            ->get();

        return view('production.menus.import', compact('brands'));
    }

    public function import(Request $request): RedirectResponse
    {
        $tenantId = $this->tenantId($request);

        $validated = $request->validate([
            'brand_id' => ['required', 'integer', Rule::exists('brands', 'id')->where('tenant_id', $tenantId)],
            'file'     => ['required', 'file', 'mimes:xlsx,xls', 'max:10240'],
        ]);

        $import = new MenuImport($tenantId, (int) $validated['brand_id']);
        Excel::import($import, $request->file('file'));
        $summary = $import->summary();

        $message = "Import menu selesai: {$summary['inserted']} baru, {$summary['updated']} diperbarui"
            .($summary['categories_created'] > 0 ? ", {$summary['categories_created']} kategori baru" : '')
            .($summary['skipped'] > 0 ? ", {$summary['skipped']} baris kosong dilewati" : '').'.';

        return redirect()
            ->route('production.menus.index')
            ->with('success', $message)
            ->with('import_errors', $summary['errors']);
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

        // Kalau kategori yang sedang dipakai menu ini sudah dinonaktifkan,
        // tetap sertakan di dropdown (ditandai nonaktif) — supaya simpan
        // perubahan field LAIN yang tidak terkait tidak diam-diam menghapus
        // menu_category_id (dropdown tanpa opsi yang cocok -> browser balik
        // ke "Tanpa kategori" -> tersimpan null tanpa error apa pun).
        if ($menu->menu_category_id && ! $categories->contains('id', $menu->menu_category_id)) {
            $current = MenuCategory::query()->where('tenant_id', $tenantId)->find($menu->menu_category_id);

            if ($current) {
                $current = clone $current;
                $current->name = $current->name.' (nonaktif)';
                $categories->push($current);
            }
        }

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

        $hardDeleted = $this->deleteOrDeactivate($menu);

        return redirect()
            ->route('production.menus.index')
            ->with('success', $hardDeleted
                ? 'Menu berhasil dihapus.'
                : 'Menu dinonaktifkan (masih punya riwayat resep, tidak bisa dihapus permanen).');
    }

    public function bulkDestroy(BulkDestroyMenuRequest $request): RedirectResponse
    {
        $tenantId = $this->tenantId($request);

        $menus = Menu::query()
            ->whereIn('id', $request->input('ids', []))
            ->where('tenant_id', $tenantId)
            ->get();

        $hardDeleted = 0;
        $deactivated = 0;

        $result = $this->runBulkAction(
            $menus,
            function (Menu $menu) use (&$hardDeleted, &$deactivated): void {
                if ($this->deleteOrDeactivate($menu)) {
                    $hardDeleted++;
                } else {
                    $deactivated++;
                }
            },
            fn (Menu $menu) => $menu->name
        );

        $message = "{$hardDeleted} menu dihapus permanen, {$deactivated} dinonaktifkan (masih punya riwayat resep).";

        return $this->bulkActionRedirect('production.menus.index', $result, $message);
    }

    /**
     * Hard-delete Menu jika belum pernah dipakai di resep manapun, kalau
     * sudah cuma dinonaktifkan -- logika APA ADANYA dari destroy() lama,
     * dipakai bareng oleh destroy() satuan dan bulkDestroy() supaya tidak
     * ada 2 tempat yang bisa berbeda perilaku.
     */
    private function deleteOrDeactivate(Menu $menu): bool
    {
        if ($menu->canHardDelete()) {
            if ($menu->photo_path) {
                Storage::disk('public')->delete($menu->photo_path);
            }

            $menu->delete();

            return true;
        }

        $menu->update(['is_active' => false]);

        return false;
    }

    private function tenantId(Request $request): int
    {
        $tenantId = $request->user()?->tenant_id;

        abort_unless($tenantId, 403, 'Tenant belum terpasang pada user.');

        return (int) $tenantId;
    }
}
