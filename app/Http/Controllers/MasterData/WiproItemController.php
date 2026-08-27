<?php

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\Controller;
use App\Modules\Inventory\Models\Item;
use App\Modules\Inventory\Models\ItemCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WiproItemController extends Controller
{
    public function index(Request $request): View
    {
        $tenantId = $this->tenantId($request);
        $search = $request->string('q')->toString();
        $categoryFilter = $request->string('category_id')->toString();

        $query = Item::query()
            ->with(['category', 'baseUnit'])
            ->where('tenant_id', $tenantId)
            ->where('item_source', 'WIPRO');

        if ($search !== '') {
            $query->where(fn ($q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('canonical_sku', 'like', "%{$search}%")
            );
        }

        if ($categoryFilter !== '') {
            $query->where('item_category_id', $categoryFilter);
        }

        $items = $query->orderBy('name')->paginate(20)->withQueryString();

        $categories = ItemCategory::query()
            ->where('tenant_id', $tenantId)
            ->whereHas('items', fn ($q) => $q->where('item_source', 'WIPRO'))
            ->orderBy('name')
            ->get();

        return view('master-data.wipro-items.index', [
            'items' => $items,
            'search' => $search,
            'categories' => $categories,
            'categoryFilter' => $categoryFilter,
        ]);
    }

    public function edit(Request $request, Item $item): View
    {
        $this->authorizeWiproItem($request, $item);

        $item->load(['category', 'baseUnit', 'inventoryUnit', 'purchaseUnit']);

        return view('master-data.wipro-items.edit', ['item' => $item]);
    }

    public function update(Request $request, Item $item): RedirectResponse
    {
        $this->authorizeWiproItem($request, $item);
        $tenantId = $this->tenantId($request);

        $validated = $request->validate([
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:2048'],
            'description' => ['nullable', 'string', 'max:2000'],
            'keterangan_pembeda' => ['nullable', 'string', 'max:255'],
        ]);

        $data = [
            'description' => $validated['description'] ?? null,
            'keterangan_pembeda' => $validated['keterangan_pembeda'] ?? null,
        ];

        if ($request->hasFile('photo')) {
            $data['photo'] = $request->file('photo')->store("tenants/{$tenantId}/items", 'public');
        }

        $item->update($data);

        return redirect()
            ->route('master-data.wipro-items.index')
            ->with('success', "Item Wipro {$item->name} berhasil diperbarui.");
    }

    private function authorizeWiproItem(Request $request, Item $item): void
    {
        $tenantId = $this->tenantId($request);

        abort_unless((int) $item->tenant_id === $tenantId, 404);
        abort_unless($item->item_source === 'WIPRO', 404);
    }

    private function tenantId(Request $request): int
    {
        $tenantId = $request->user()?->tenant_id;

        abort_unless($tenantId, 403, 'Tenant belum terpasang pada user.');

        return (int) $tenantId;
    }
}
