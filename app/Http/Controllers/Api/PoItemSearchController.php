<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Inventory\Models\Item;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PoItemSearchController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $tenantId = (int) $request->user()?->tenant_id;

        abort_unless($tenantId, 403);

        $q      = (string) $request->string('q');
        $poType = (string) $request->string('po_type');

        if (mb_strlen($q) < 1) {
            return response()->json([]);
        }

        $items = Item::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->when(
                $poType === 'CENTRAL_KITCHEN',
                fn ($q) => $q->where('item_source', 'WIPRO'),
                fn ($q) => $q->where('track_stock', true)->forPoType($poType),
            )
            ->where(function ($query) use ($q) {
                $query->where('name', 'like', '%'.$q.'%')
                      ->orWhere('canonical_sku', 'like', '%'.$q.'%');
            })
            ->with('inventoryUnit')
            ->orderBy('name')
            ->limit(25)
            ->get(['id', 'name', 'canonical_sku', 'inventory_unit_id'])
            ->map(fn (Item $item) => [
                'id'        => $item->id,
                'name'      => $item->name,
                'sku'       => $item->canonical_sku,
                'unit_id'   => $item->inventory_unit_id,
                'unit_code' => $item->inventoryUnit?->code ?? '',
                'unit_name' => $item->inventoryUnit?->name ?? '',
            ]);

        return response()->json($items);
    }
}
