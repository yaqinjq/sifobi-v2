<?php

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\Concerns\HasBulkAction;
use App\Http\Controllers\Concerns\HasPerPageSelector;
use App\Http\Controllers\Concerns\ResolvesTableSort;
use App\Http\Controllers\Controller;
use App\Http\Requests\MasterData\BulkActivateOciaItemForOpnameRequest;
use App\Http\Requests\MasterData\BulkDeactivateOciaItemForOpnameRequest;
use App\Modules\Core\Models\Department;
use App\Modules\Core\Models\Outlet;
use App\Modules\Inventory\Models\Item;
use App\Modules\Inventory\Models\ItemCategory;
use App\Modules\Inventory\Models\ItemOutlet;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Mirip persis WiproItemController -- lihat file itu untuk penjelasan
 * lengkap pola "aktifkan untuk Opname" & proteksi kustomisasi manual.
 * Item di sini berasal dari sync katalog OCIA (lihat OciaCatalogSyncService),
 * bukan dari upload Excel seperti Wipro.
 */
class OciaItemController extends Controller
{
    use HasBulkAction;
    use HasPerPageSelector;
    use ResolvesTableSort;

    public function index(Request $request): View
    {
        $tenantId = $this->tenantId($request);
        $search = $request->string('q')->toString();
        $categoryFilter = $request->string('category_id')->toString();
        [$perPage, $perPageOptions] = $this->perPageAndOptions($request, 20);
        [$sort, $direction] = $this->resolveTableSort($request, ['canonical_sku', 'name', 'is_active'], 'name');

        $query = Item::query()
            ->with(['category', 'baseUnit', 'primaryDepartment'])
            ->where('tenant_id', $tenantId)
            ->where('item_source', 'OCIA');

        if ($search !== '') {
            $query->where(fn ($q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('canonical_sku', 'like', "%{$search}%")
            );
        }

        if ($categoryFilter !== '') {
            $query->where('item_category_id', $categoryFilter);
        }

        $items = $query->orderBy($sort, $direction)->paginate($perPage)->withQueryString();

        $categories = ItemCategory::query()
            ->where('tenant_id', $tenantId)
            ->whereHas('items', fn ($q) => $q->where('item_source', 'OCIA'))
            ->orderBy('name')
            ->get();

        return view('master-data.ocia-items.index', [
            'items' => $items,
            'search' => $search,
            'categories' => $categories,
            'categoryFilter' => $categoryFilter,
            'perPage' => $perPage,
            'perPageOptions' => $perPageOptions,
            'sort' => $sort,
            'direction' => $direction,
            'opnameDepartments' => Department::query()
                ->where('tenant_id', $tenantId)
                ->where('status', 'ACTIVE')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function bulkActivateForOpname(BulkActivateOciaItemForOpnameRequest $request): RedirectResponse
    {
        $tenantId = $this->tenantId($request);

        $department = Department::query()
            ->where('tenant_id', $tenantId)
            ->findOrFail($request->input('department_id'));

        $items = Item::query()
            ->whereIn('id', $request->input('ids', []))
            ->where('tenant_id', $tenantId)
            ->where('item_source', 'OCIA')
            ->get();

        $outletIds = Outlet::query()
            ->where('tenant_id', $tenantId)
            ->where('status', 'ACTIVE')
            ->pluck('id');

        $result = $this->runBulkAction(
            $items,
            function (Item $item) use ($department, $tenantId, $outletIds): void {
                $item->update([
                    'track_stock' => true,
                    'primary_department_id' => $department->id,
                ]);

                $effectiveCategoryId = $item->categoryForDepartment($department->id)?->id;

                if ($effectiveCategoryId) {
                    $department->itemCategories()->syncWithoutDetaching([$effectiveCategoryId]);
                }

                foreach ($outletIds as $outletId) {
                    ItemOutlet::query()->updateOrCreate(
                        ['tenant_id' => $tenantId, 'item_id' => $item->id, 'outlet_id' => $outletId],
                        ['is_active' => true]
                    );
                }
            },
            fn (Item $item) => $item->name
        );

        return $this->bulkActionRedirect(
            'master-data.ocia-items.index',
            $result,
            "{$result['processed']} item OCIA diaktifkan untuk Opname di departemen {$department->name}."
        );
    }

    public function bulkDeactivateForOpname(BulkDeactivateOciaItemForOpnameRequest $request): RedirectResponse
    {
        $tenantId = $this->tenantId($request);

        $items = Item::query()
            ->whereIn('id', $request->input('ids', []))
            ->where('tenant_id', $tenantId)
            ->where('item_source', 'OCIA')
            ->get();

        $result = $this->runBulkAction(
            $items,
            fn (Item $item) => $item->update([
                'track_stock' => false,
                'primary_department_id' => null,
            ]),
            fn (Item $item) => $item->name
        );

        return $this->bulkActionRedirect(
            'master-data.ocia-items.index',
            $result,
            "{$result['processed']} item OCIA dinonaktifkan dari Opname."
        );
    }

    public function edit(Request $request, Item $item): View
    {
        $this->authorizeOciaItem($request, $item);

        $item->load(['category', 'baseUnit', 'inventoryUnit', 'purchaseUnit']);

        return view('master-data.ocia-items.edit', ['item' => $item]);
    }

    public function update(Request $request, Item $item): RedirectResponse
    {
        $this->authorizeOciaItem($request, $item);
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
            ->route('master-data.ocia-items.index')
            ->with('success', "Item OCIA {$item->name} berhasil diperbarui.");
    }

    private function authorizeOciaItem(Request $request, Item $item): void
    {
        $tenantId = $this->tenantId($request);

        abort_unless((int) $item->tenant_id === $tenantId, 404);
        abort_unless($item->item_source === 'OCIA', 404);
    }

    private function tenantId(Request $request): int
    {
        $tenantId = $request->user()?->tenant_id;

        abort_unless($tenantId, 403, 'Tenant belum terpasang pada user.');

        return (int) $tenantId;
    }
}
