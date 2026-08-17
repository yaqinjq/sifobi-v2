<?php

namespace App\Http\Controllers\Production;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Core\Models\Outlet;
use App\Modules\Inventory\Models\Item;
use App\Modules\Inventory\Models\Unit;
use App\Modules\Inventory\Models\UnitConversion;
use App\Modules\Production\Models\Menu;
use App\Modules\Production\Models\Recipe;
use App\Services\RecipeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RecipeController extends Controller
{
    public function __construct(private readonly RecipeService $service) {}

    public function create(Request $request, Menu $menu): View
    {
        $tenantId = $this->tenantId($request);

        abort_unless((int) $menu->tenant_id === $tenantId, 403);

        return view('production.recipes.form', [
            'menu'   => $menu,
            'recipe' => null,
            'items'  => $this->itemsForPicker($tenantId),
            'units'  => Unit::query()->where('tenant_id', $tenantId)->orderBy('name')->get(['id', 'name', 'code']),
            'users'  => User::query()->where('tenant_id', $tenantId)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request, Menu $menu): RedirectResponse
    {
        $tenantId = $this->tenantId($request);

        abort_unless((int) $menu->tenant_id === $tenantId, 403);

        $validated = $this->validated($request, $tenantId);

        $recipe = $this->service->createDraft(
            array_merge($validated['recipe'], ['tenant_id' => $tenantId, 'menu_id' => $menu->id]),
            $validated['ingredients'],
            $validated['other_costs'],
            (int) $request->user()->id
        );

        return redirect()
            ->route('production.recipes.show', $recipe)
            ->with('success', 'Draft resep berhasil dibuat.');
    }

    public function show(Request $request, Recipe $recipe): View
    {
        $tenantId = $this->tenantId($request);

        abort_unless((int) $recipe->tenant_id === $tenantId, 403);

        $recipe->load([
            'menu.brand',
            'createdBy',
            'approvedBy',
            'ingredients.item',
            'ingredients.buyUnit',
            'ingredients.recipeUnit',
            'otherCosts',
            'outlets',
            'approvalEvents.actor',
        ]);

        $outlets = Outlet::query()->where('tenant_id', $tenantId)->where('status', 'ACTIVE')->orderBy('name')->get(['id', 'name']);

        return view('production.recipes.show', [
            'recipe'  => $recipe,
            'hpp'     => $recipe->hpp(),
            'outlets' => $outlets,
        ]);
    }

    public function edit(Request $request, Recipe $recipe): View
    {
        $tenantId = $this->tenantId($request);

        abort_unless((int) $recipe->tenant_id === $tenantId, 403);
        abort_unless($recipe->canEdit(), 403);

        $recipe->load(['ingredients', 'otherCosts', 'menu']);

        return view('production.recipes.form', [
            'menu'   => $recipe->menu,
            'recipe' => $recipe,
            'items'  => $this->itemsForPicker($tenantId),
            'units'  => Unit::query()->where('tenant_id', $tenantId)->orderBy('name')->get(['id', 'name', 'code']),
            'users'  => User::query()->where('tenant_id', $tenantId)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(Request $request, Recipe $recipe): RedirectResponse
    {
        $tenantId = $this->tenantId($request);

        abort_unless((int) $recipe->tenant_id === $tenantId, 403);

        $validated = $this->validated($request, $tenantId);

        $this->service->updateDraft($recipe, $validated['recipe'], $validated['ingredients'], $validated['other_costs']);

        return redirect()
            ->route('production.recipes.show', $recipe)
            ->with('success', 'Draft resep berhasil diperbarui.');
    }

    public function submit(Request $request, Recipe $recipe): RedirectResponse
    {
        $tenantId = $this->tenantId($request);

        abort_unless((int) $recipe->tenant_id === $tenantId, 403);

        try {
            $this->service->submit($recipe, (int) $request->user()->id);
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors());
        }

        return redirect()
            ->route('production.recipes.show', $recipe)
            ->with('success', 'Resep berhasil diajukan untuk persetujuan.');
    }

    public function approve(Request $request, Recipe $recipe): RedirectResponse
    {
        $tenantId = $this->tenantId($request);

        abort_unless((int) $recipe->tenant_id === $tenantId, 403);

        $validated = $request->validate([
            'outlet_ids'   => ['required', 'array', 'min:1'],
            'outlet_ids.*' => ['integer', Rule::exists('outlets', 'id')->where('tenant_id', $tenantId)],
        ]);

        try {
            $this->service->approve($recipe, (int) $request->user()->id, array_map('intval', $validated['outlet_ids']));
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors());
        }

        return redirect()
            ->route('production.recipes.show', $recipe)
            ->with('success', 'Resep disetujui dan diterapkan ke outlet terpilih.');
    }

    public function reject(Request $request, Recipe $recipe): RedirectResponse
    {
        $tenantId = $this->tenantId($request);

        abort_unless((int) $recipe->tenant_id === $tenantId, 403);

        $validated = $request->validate([
            'rejected_reason' => ['required', 'string', 'min:5', 'max:2000'],
        ]);

        $this->service->reject($recipe, (int) $request->user()->id, $validated['rejected_reason']);

        return redirect()
            ->route('production.recipes.show', $recipe)
            ->with('success', 'Resep ditolak.');
    }

    /**
     * @return array{recipe: array<string, mixed>, ingredients: array<int, array<string, mixed>>, other_costs: array<int, array<string, mixed>>}
     */
    private function validated(Request $request, int $tenantId): array
    {
        $validated = $request->validate([
            'test_date'                  => ['nullable', 'date'],
            'witnessed_by_user_ids'      => ['nullable', 'array'],
            'witnessed_by_user_ids.*'    => ['integer', Rule::exists('users', 'id')->where('tenant_id', $tenantId)],
            'witnessed_by_names'         => ['nullable', 'string', 'max:500'],
            'food_panel_user_ids'        => ['nullable', 'array'],
            'food_panel_user_ids.*'      => ['integer', Rule::exists('users', 'id')->where('tenant_id', $tenantId)],
            'food_panel_names'           => ['nullable', 'string', 'max:500'],
            'volume_production'          => ['required', 'numeric', 'min:0.0001'],
            'notes'                      => ['nullable', 'string', 'max:2000'],

            'ingredients'                     => ['nullable', 'array'],
            'ingredients.*.item_id'          => ['required', 'integer', Rule::exists('items', 'id')->where('tenant_id', $tenantId)],
            'ingredients.*.buy_qty'          => ['required', 'numeric', 'min:0.000001'],
            'ingredients.*.buy_unit_id'      => ['required', 'integer', Rule::exists('units', 'id')->where('tenant_id', $tenantId)],
            'ingredients.*.buy_price'        => ['required', 'numeric', 'min:0'],
            'ingredients.*.recipe_qty'       => ['required', 'numeric', 'min:0.000001'],
            'ingredients.*.recipe_unit_id'   => ['required', 'integer', Rule::exists('units', 'id')->where('tenant_id', $tenantId)],

            'other_costs'                => ['nullable', 'array'],
            'other_costs.*.cost_type'    => ['required', Rule::in(['PRODUCTION', 'OVERHEAD'])],
            'other_costs.*.label'        => ['required', 'string', 'max:150'],
            'other_costs.*.amount'       => ['required', 'numeric', 'min:0'],
        ]);

        return [
            'recipe' => [
                'test_date'             => $validated['test_date'] ?? null,
                'witnessed_by_user_ids' => $validated['witnessed_by_user_ids'] ?? null,
                'witnessed_by_names'    => $validated['witnessed_by_names'] ?? null,
                'food_panel_user_ids'   => $validated['food_panel_user_ids'] ?? null,
                'food_panel_names'      => $validated['food_panel_names'] ?? null,
                'volume_production'     => $validated['volume_production'],
                'notes'                 => $validated['notes'] ?? null,
            ],
            'ingredients' => $validated['ingredients'] ?? [],
            'other_costs' => $validated['other_costs'] ?? [],
        ];
    }

    /**
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function itemsForPicker(int $tenantId)
    {
        $conversions = UnitConversion::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->get(['item_id', 'from_unit_id', 'to_unit_id', 'factor'])
            ->groupBy('item_id');

        return Item::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'canonical_sku', 'base_unit_id', 'inventory_unit_id', 'inventory_ratio', 'purchase_unit_id', 'purchase_ratio'])
            ->map(fn (Item $item) => [
                'id'                => $item->id,
                'name'              => $item->name,
                'sku'               => $item->canonical_sku,
                'base_unit_id'      => $item->base_unit_id,
                'inventory_unit_id' => $item->inventory_unit_id,
                'inventory_ratio'   => (float) ($item->inventory_ratio ?? 1),
                'purchase_unit_id'  => $item->purchase_unit_id,
                'purchase_ratio'    => (float) ($item->purchase_ratio ?? 1),
                // Konversi kustom (di luar base/inventory/purchase) untuk item ini —
                // dipakai kalkulator HPP real-time di form supaya hasilnya sama
                // persis dengan perhitungan server (RecipeIngredient::toBaseQty()).
                'conversions' => ($conversions->get($item->id) ?? collect())->map(fn ($c) => [
                    'from_unit_id' => $c->from_unit_id,
                    'to_unit_id'   => $c->to_unit_id,
                    'factor'       => (float) $c->factor,
                ])->values(),
            ]);
    }

    private function tenantId(Request $request): int
    {
        $tenantId = $request->user()?->tenant_id;

        abort_unless($tenantId, 403, 'Tenant belum terpasang pada user.');

        return (int) $tenantId;
    }
}
