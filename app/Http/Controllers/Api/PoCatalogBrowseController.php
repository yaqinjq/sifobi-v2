<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Inventory\Models\Item;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Returns all catalog items for a given PO type, grouped by category.
 * Used for marketplace-style browse on the CK tab (Wipro items).
 */
class PoCatalogBrowseController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $tenantId = (int) $request->user()?->tenant_id;

        abort_unless($tenantId, 403);

        $poType = (string) $request->string('po_type');

        $items = Item::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->where(function ($q) {
                $q->where('item_source', 'WIPRO')->orWhere('track_stock', true);
            })
            ->forPoType($poType)
            ->with(['inventoryUnit', 'category'])
            ->orderBy('name')
            ->get(['id', 'name', 'canonical_sku', 'inventory_unit_id', 'item_category_id', 'item_source'])
            ->map(fn (Item $item) => [
                'id'            => $item->id,
                'name'          => $item->name,
                'sku'           => $item->canonical_sku,
                'unit_id'       => $item->inventory_unit_id,
                'unit_code'     => $item->inventoryUnit?->code ?? '',
                'unit_name'     => $item->inventoryUnit?->name ?? '',
                'category'      => $item->category?->name ?? 'Lainnya',
                'category_raw'  => $item->category?->code ?? 'LAINNYA',
                'item_source'   => $item->item_source,
            ]);

        // Group by category
        $grouped = $items->groupBy('category')->map(fn ($group) => $group->values())->sortKeys();

        return response()->json($grouped);
    }
}
