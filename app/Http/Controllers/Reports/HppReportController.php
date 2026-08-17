<?php

namespace App\Http\Controllers\Reports;

use App\Exports\Reports\HppExport;
use App\Http\Controllers\Controller;
use App\Modules\Core\Models\Brand;
use App\Modules\Production\Models\Menu;
use App\Modules\Production\Models\Recipe;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class HppReportController extends Controller
{
    public function index(Request $request): View
    {
        $tenantId = $this->tenantId($request);
        $filters = $this->filters($request);

        $recipes = $this->recipes($tenantId, $filters)->paginate(20)->withQueryString();

        return view('laporan.hpp', [
            'recipes' => $recipes,
            'brands'  => Brand::query()->where('tenant_id', $tenantId)->orderBy('name')->get(),
            'menus'   => Menu::query()->where('tenant_id', $tenantId)->orderBy('name')->get(),
            'filters' => $filters,
        ]);
    }

    public function export(Request $request): BinaryFileResponse
    {
        $tenantId = $this->tenantId($request);
        $filters = $this->filters($request);

        return Excel::download(new HppExport($tenantId, $filters), 'LaporanHPP.xlsx');
    }

    /**
     * @return array<string, mixed>
     */
    private function filters(Request $request): array
    {
        return [
            'brand_id' => $request->integer('brand_id') ?: null,
            'menu_id'  => $request->integer('menu_id') ?: null,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function recipes(int $tenantId, array $filters)
    {
        return Recipe::query()
            ->where('tenant_id', $tenantId)
            ->where('status', Recipe::STATUS_APPROVED)
            ->with(['menu.brand', 'ingredients.item', 'ingredients.buyUnit', 'ingredients.recipeUnit', 'otherCosts', 'outlets'])
            ->when($filters['menu_id'] ?? null, fn ($q, $menuId) => $q->where('menu_id', $menuId))
            ->when($filters['brand_id'] ?? null, fn ($q, $brandId) => $q->whereHas('menu', fn ($mq) => $mq->where('brand_id', $brandId)))
            ->orderByDesc('approved_at');
    }

    private function tenantId(Request $request): int
    {
        $tenantId = $request->user()?->tenant_id;
        abort_unless($tenantId, 403);

        return (int) $tenantId;
    }
}
