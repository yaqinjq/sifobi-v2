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
            ->where('item_source', 'WIPRO')
            ->with(['purchaseUnit', 'baseUnit', 'category'])
            ->orderBy('name')
            ->get(['id', 'name', 'canonical_sku', 'purchase_unit_id', 'base_unit_id', 'purchase_ratio', 'item_category_id', 'item_source'])
            ->map(function (Item $item) {
                // Order-nya pakai purchase_unit (mis. PACK), bukan inventory_unit —
                // kalau purchase_unit beda dari base_unit (ada data konversi dari
                // tim WIP), tampilkan hint "1 PACK = N KG" di gray subtitle.
                $hasConversion = $item->purchase_unit_id && $item->base_unit_id
                    && $item->purchase_unit_id !== $item->base_unit_id;

                return [
                    'id'              => $item->id,
                    'name'            => $item->name,
                    'sku'             => $item->canonical_sku,
                    'unit_id'         => $item->purchase_unit_id,
                    'unit_code'       => $item->purchaseUnit?->code ?? '',
                    'unit_name'       => $item->purchaseUnit?->name ?? '',
                    'conversion_hint' => $hasConversion
                        ? '1 '.$item->purchaseUnit?->code.' = '.rtrim(rtrim((string) $item->purchase_ratio, '0'), '.').' '.$item->baseUnit?->code
                        : null,
                    'category'      => $item->category?->name ?? 'Lainnya',
                    'category_raw'  => $item->category?->code ?? 'LAINNYA',
                    'item_source'   => $item->item_source,
                ];
            });

        // Group by category
        $grouped = $items->groupBy('category')->map(fn ($group) => $group->values())->sortKeys();

        return response()->json($grouped);
    }
}
