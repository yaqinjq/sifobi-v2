<?php

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\Controller;
use App\Http\Requests\MasterData\StoreItemRequest;
use App\Http\Requests\MasterData\UpdateItemRequest;
use App\Modules\Core\Models\Brand;
use App\Modules\Core\Models\Department;
use App\Modules\Core\Models\Outlet;
use App\Modules\Inventory\Models\Item;
use App\Modules\Inventory\Models\ItemBrandAlias;
use App\Modules\Inventory\Models\ItemCategory;
use App\Modules\Inventory\Models\ItemJenis;
use App\Modules\Inventory\Models\Unit;
use App\Support\Decimal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ItemController extends Controller
{
    public function index(Request $request): View
    {
        $tenantId = $this->tenantId($request);
        $search = $request->string('q')->toString();
        $typeFilter = $request->string('type')->toString();
        $statusFilter = $request->string('status')->toString();
        $sortable = ['canonical_sku', 'name', 'item_jenis_id', 'item_category_id', 'item_type', 'created_at'];
        $sort = in_array($request->string('sort')->toString(), $sortable, true)
            ? $request->string('sort')->toString()
            : 'name';
        $direction = $request->string('direction')->toString() === 'desc' ? 'desc' : 'asc';

        $query = Item::query()
            ->with(['baseUnit', 'inventoryUnit', 'purchaseUnit', 'category', 'primaryDepartment', 'departments', 'jenis'])
            ->withCount('outlets')
            ->where('tenant_id', $tenantId);

        if ($typeFilter !== '') {
            $query->where('item_type', $typeFilter);
        }

        if ($statusFilter !== '') {
            $query->where('is_active', $statusFilter === 'active');
        }

        if ($search !== '') {
            $query->where(fn ($q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('canonical_sku', 'like', "%{$search}%")
            );
        }

        $items = $query
            ->orderBy($sort, $direction)
            ->paginate(25)
            ->withQueryString();

        return view('master-data.items.index', [
            'items' => $items,
            'itemTypes' => StoreItemRequest::ITEM_TYPE_OPTIONS,
            'totalItems' => Item::query()->where('tenant_id', $tenantId)->count(),
            'search' => $search,
            'typeFilter' => $typeFilter,
            'statusFilter' => $statusFilter,
            'sort' => $sort,
            'direction' => $direction,
        ]);
    }

    public function create(Request $request): View
    {
        return view('master-data.items.create', array_merge($this->formData($request), [
            'item' => new Item([
                'is_active' => true,
                'item_type' => 'BAHAN_BAKU',
                'opname_frequency' => 'DAILY',
                'yield_pct' => '100.00',
            ]),
        ]));
    }

    public function store(StoreItemRequest $request): RedirectResponse
    {
        $item = DB::transaction(function () use ($request): Item {
            $data = $request->payload();
            $tenantId = (int) $request->user()->tenant_id;

            if ($request->hasFile('photo')) {
                $data['photo'] = $request->file('photo')->store("tenants/{$tenantId}/items", 'public');
            }

            $item = Item::query()->create($data);
            $this->syncRelations($item, $request, $tenantId);
            $this->syncExtraConversions($item, $request, $tenantId);

            return $item;
        });

        return redirect()
            ->route('master-data.items.show', $item)
            ->with('success', "Item {$item->name} berhasil ditambahkan.");
    }

    public function show(Request $request, Item $item): View
    {
        $this->authorizeTenant($request, $item);

        $item->load([
            'baseUnit',
            'inventoryUnit',
            'purchaseUnit',
            'category',
            'jenis',
            'primaryDepartment',
            'departments',
            'outlets',
            'brandAliases.brand',
            'conversions.fromUnit',
            'conversions.toUnit',
        ]);

        return view('master-data.items.show', array_merge($this->formData($request), [
            'item' => $item,
            'aliases' => $item->brandAliases->map(fn (ItemBrandAlias $alias): array => $this->aliasPayload($alias))->values(),
            'conversions' => $item->conversions->map(fn ($conversion): array => [
                'id' => $conversion->id,
                'from_unit' => $conversion->fromUnit?->code,
                'to_unit' => $conversion->toUnit?->code,
                'factor' => rtrim(rtrim((string) ($conversion->factor ?? $conversion->multiply_rate), '0'), '.'),
                'destroy_url' => route('master-data.items.conversions.destroy', [$item, $conversion]),
            ])->values(),
        ]));
    }

    public function edit(Request $request, Item $item): View
    {
        $this->authorizeTenant($request, $item);

        return view('master-data.items.edit', array_merge($this->formData($request), [
            'item' => $item->load(['baseUnit', 'inventoryUnit', 'purchaseUnit', 'category', 'jenis', 'primaryDepartment', 'departments', 'outlets', 'brandAliases.brand', 'conversions.fromUnit', 'conversions.toUnit']),
        ]));
    }

    public function update(UpdateItemRequest $request, Item $item): RedirectResponse
    {
        $this->authorizeTenant($request, $item);

        DB::transaction(function () use ($request, $item): void {
            $data = $request->payload();
            $tenantId = (int) $request->user()->tenant_id;

            if ($request->hasFile('photo')) {
                $data['photo'] = $request->file('photo')->store("tenants/{$tenantId}/items", 'public');
            }

            $item->update($data);
            $this->syncRelations($item, $request, $tenantId);
            $this->syncExtraConversions($item, $request, $tenantId);
        });

        return redirect()
            ->route('master-data.items.show', $item)
            ->with('success', 'Item berhasil diperbarui.');
    }

    public function destroy(Request $request, Item $item): RedirectResponse
    {
        $this->authorizeTenant($request, $item);

        DB::transaction(fn () => $item->update(['is_active' => false]));

        return redirect()
            ->route('master-data.items.index')
            ->with('success', 'Item berhasil dinonaktifkan.');
    }

    public function toggleStatus(Request $request, Item $item): RedirectResponse
    {
        abort_unless($request->user()?->can('manage_items'), 403);
        $this->authorizeTenant($request, $item);
        $willBeActive = ! $item->is_active;

        DB::transaction(fn () => $item->update(['is_active' => $willBeActive]));

        return back()->with('success', $willBeActive ? 'Item berhasil diaktifkan.' : 'Item berhasil dinonaktifkan.');
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(Request $request): array
    {
        $tenantId = $this->tenantId($request);

        return [
            'units' => Unit::query()
                ->where('tenant_id', $tenantId)
                ->orderBy('name')
                ->get(),
            'departments' => Department::query()
                ->where('tenant_id', $tenantId)
                ->where('status', 'ACTIVE')
                ->orderBy('name')
                ->get(),
            'outlets' => Outlet::query()
                ->where('tenant_id', $tenantId)
                ->where('status', 'ACTIVE')
                ->orderBy('name')
                ->get(),
            'brands' => Brand::query()
                ->where('tenant_id', $tenantId)
                ->where('status', 'ACTIVE')
                ->orderBy('name')
                ->get(),
            'jenises' => ItemJenis::query()
                ->where('tenant_id', $tenantId)
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
            'categories' => ItemCategory::query()
                ->where('tenant_id', $tenantId)
                ->where('is_active', true)
                ->where('status', 'ACTIVE')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
            'itemTypes' => StoreItemRequest::ITEM_TYPE_OPTIONS,
            'opnameFrequencies' => [
                'DAILY' => 'Harian',
                'WEEKLY' => 'Mingguan',
                'MONTHLY' => 'Bulanan',
            ],
        ];
    }

    private function tenantId(Request $request): int
    {
        $tenantId = $request->user()?->tenant_id;

        abort_unless($tenantId, 403, 'Tenant belum terpasang pada user.');

        return (int) $tenantId;
    }

    private function authorizeTenant(Request $request, Item $item): void
    {
        abort_unless($item->tenant_id === $this->tenantId($request), 404);
    }

    private function syncRelations(Item $item, Request $request, int $tenantId): void
    {
        $item->departments()->sync($request->input('department_ids', []));

        $outletPivot = [];
        foreach ($request->input('outlet_ids', []) as $outletId) {
            $outletPivot[(int) $outletId] = [
                'tenant_id' => $tenantId,
                'status' => 'ACTIVE',
                'opname_frequency' => null,
            ];
        }

        $item->outlets()->sync($outletPivot);
    }

    private function syncExtraConversions(Item $item, Request $request, int $tenantId): void
    {
        if (! $request->boolean('sync_extra_conversions')) {
            return;
        }

        $item->conversions()->delete();

        foreach ($request->input('extra_conversions', []) as $conversion) {
            if (
                empty($conversion['from_unit_id'])
                || empty($conversion['to_unit_id'])
                || empty($conversion['factor'])
                || (int) $conversion['from_unit_id'] === (int) $conversion['to_unit_id']
            ) {
                continue;
            }

            $factor = Decimal::toFixed($conversion['factor'], 8);

            $item->conversions()->create([
                'tenant_id' => $tenantId,
                'from_unit_id' => (int) $conversion['from_unit_id'],
                'to_unit_id' => (int) $conversion['to_unit_id'],
                'multiply_rate' => $factor,
                'factor' => $factor,
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function aliasPayload(ItemBrandAlias $alias): array
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
}
