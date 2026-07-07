<?php

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\Controller;
use App\Modules\Inventory\Models\Item;
use App\Modules\Inventory\Models\ItemBrandAlias;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ItemAliasController extends Controller
{
    public function store(Request $request, Item $item): JsonResponse
    {
        $tenantId = $this->tenantId($request);
        $this->authorizeTenant($item, $tenantId);

        $request->merge([
            'brand_sku' => strtoupper(trim((string) $request->input('brand_sku'))),
        ]);

        $validated = $request->validate([
            'brand_id' => ['required', 'integer', Rule::exists('brands', 'id')->where('tenant_id', $tenantId)],
            'brand_sku' => [
                'required',
                'string',
                'max:100',
                Rule::unique('item_brand_aliases', 'brand_sku')
                    ->where('tenant_id', $tenantId)
                    ->where('brand_id', (int) $request->input('brand_id')),
            ],
            'brand_item_name' => ['nullable', 'string', 'max:255'],
            'is_primary' => ['nullable', 'boolean'],
        ]);

        $alias = DB::transaction(function () use ($item, $tenantId, $validated, $request): ItemBrandAlias {
            $hasAliasForBrand = ItemBrandAlias::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('item_id', $item->id)
                ->where('brand_id', (int) $validated['brand_id'])
                ->exists();

            $isPrimary = $request->boolean('is_primary') || ! $hasAliasForBrand;

            if ($isPrimary) {
                ItemBrandAlias::withoutGlobalScopes()
                    ->where('tenant_id', $tenantId)
                    ->where('item_id', $item->id)
                    ->where('brand_id', (int) $validated['brand_id'])
                    ->update(['is_primary' => false]);
            }

            return ItemBrandAlias::withoutGlobalScopes()->create([
                'tenant_id' => $tenantId,
                'item_id' => $item->id,
                'brand_id' => (int) $validated['brand_id'],
                'brand_sku' => strtoupper(trim($validated['brand_sku'])),
                'brand_item_name' => $validated['brand_item_name'] ?? null,
                'is_primary' => $isPrimary,
            ]);
        });

        $alias->load('brand');

        return response()->json([
            'success' => true,
            'alias' => $this->payload($alias),
        ]);
    }

    public function destroy(Request $request, Item $item, ItemBrandAlias $alias): JsonResponse
    {
        $tenantId = $this->tenantId($request);
        $this->authorizeTenant($item, $tenantId);
        abort_unless((int) $alias->tenant_id === $tenantId && (int) $alias->item_id === (int) $item->id, 404);

        DB::transaction(fn () => $alias->delete());

        return response()->json(['success' => true]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(ItemBrandAlias $alias): array
    {
        return [
            'id' => $alias->id,
            'brand_id' => $alias->brand_id,
            'brand_name' => $alias->brand?->name,
            'brand_sku' => $alias->brand_sku,
            'brand_item_name' => $alias->brand_item_name,
            'is_primary' => (bool) $alias->is_primary,
            'destroy_url' => route('master-data.items.aliases.destroy', [$alias->item_id, $alias]),
        ];
    }

    private function tenantId(Request $request): int
    {
        $tenantId = $request->user()?->tenant_id;

        abort_unless($tenantId, 403, 'Tenant belum terpasang pada user.');

        return (int) $tenantId;
    }

    private function authorizeTenant(Item $item, int $tenantId): void
    {
        abort_unless((int) $item->tenant_id === $tenantId, 404);
    }
}
