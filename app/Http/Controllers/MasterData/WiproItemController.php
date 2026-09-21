<?php

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\Concerns\HasBulkAction;
use App\Http\Controllers\Concerns\HasPerPageSelector;
use App\Http\Controllers\Controller;
use App\Http\Requests\MasterData\BulkActivateWiproItemForOpnameRequest;
use App\Modules\Core\Models\Department;
use App\Modules\Core\Models\Outlet;
use App\Modules\Inventory\Models\Item;
use App\Modules\Inventory\Models\ItemCategory;
use App\Modules\Inventory\Models\ItemOutlet;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WiproItemController extends Controller
{
    use HasBulkAction;
    use HasPerPageSelector;

    public function index(Request $request): View
    {
        $tenantId = $this->tenantId($request);
        $search = $request->string('q')->toString();
        $categoryFilter = $request->string('category_id')->toString();
        [$perPage, $perPageOptions] = $this->perPageAndOptions($request, 20);

        $query = Item::query()
            ->with(['category', 'baseUnit', 'primaryDepartment'])
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

        $items = $query->orderBy('name')->paginate($perPage)->withQueryString();

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
            'perPage' => $perPage,
            'perPageOptions' => $perPageOptions,
            'opnameDepartments' => Department::query()
                ->where('tenant_id', $tenantId)
                ->where('status', 'ACTIVE')
                ->orderBy('name')
                ->get(),
        ]);
    }

    /**
     * Aktifkan item Wipro terpilih supaya ikut muncul di Opname:
     * 1. Isi track_stock=true + primary_department_id (dua kolom yang sudah
     *    ada di skema tapi tidak pernah diisi oleh WiproCatalogImport).
     * 2. Daftarkan kategori item ke Department::itemCategories() supaya
     *    langsung muncul juga di filter Kategori & urutan "Sesuai Form".
     * 3. Pastikan ada baris item_outlets (aktif) untuk setiap outlet tenant
     *    ini -- OpnameService::itemsForOpname() memfilter berdasarkan tabel
     *    ini KALAU outlet tsb sudah punya baris item_outlets lain (mis. dari
     *    data lama/legacy migration); tanpa baris ini item Wipro yang baru
     *    diaktifkan tidak akan pernah muncul di Opname outlet tsb walau
     *    track_stock & departemennya sudah benar.
     *
     * track_stock=true otomatis kena proteksi "isCustomized" di
     * WiproCatalogImport, jadi aman dari ke-reset saat import katalog
     * berikutnya -- lihat app/Imports/WiproCatalogImport.php.
     */
    public function bulkActivateForOpname(BulkActivateWiproItemForOpnameRequest $request): RedirectResponse
    {
        $tenantId = $this->tenantId($request);

        $department = Department::query()
            ->where('tenant_id', $tenantId)
            ->findOrFail($request->input('department_id'));

        $items = Item::query()
            ->whereIn('id', $request->input('ids', []))
            ->where('tenant_id', $tenantId)
            ->where('item_source', 'WIPRO')
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

                if ($item->item_category_id) {
                    $department->itemCategories()->syncWithoutDetaching([$item->item_category_id]);
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
            'master-data.wipro-items.index',
            $result,
            "{$result['processed']} item Wipro diaktifkan untuk Opname di departemen {$department->name}."
        );
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
