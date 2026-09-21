<?php

namespace App\Http\Controllers\Operations;

use App\Exports\Reports\OpenStockExport;
use App\Http\Controllers\Concerns\HasPerPageSelector;
use App\Http\Controllers\Controller;
use App\Http\Requests\Operations\BulkPostOpenStockRequest;
use App\Http\Requests\Operations\PostOpenStockRequest;
use App\Http\Requests\Operations\StoreBulkOpenStockRequest;
use App\Http\Requests\Operations\UpdateOpenStockRequest;
use App\Http\Requests\Operations\VoidOpenStockRequest;
use App\Models\User;
use App\Modules\Core\Models\Brand;
use App\Modules\Core\Models\Department;
use App\Modules\Core\Models\Outlet;
use App\Modules\Inventory\Models\Item;
use App\Modules\Operations\Models\OpenStock;
use App\Services\OpenStockService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class OpenStockController extends Controller
{
    use HasPerPageSelector;

    public function __construct(private readonly OpenStockService $openStockService)
    {
    }

    public function index(Request $request): View
    {
        $tenantId = $request->user()->tenant_id;
        $sortable = [
            'date',
            'outlet_code',
            'department_name',
            'item_name',
            'stock_target',
            'qty_whole',
            'qty_loose',
            'qty_in_base_unit',
            'status',
        ];
        $sort = in_array($request->string('sort')->toString(), $sortable, true)
            ? $request->string('sort')->toString()
            : 'date';
        $direction = $request->string('direction')->toString() === 'asc' ? 'asc' : 'desc';

        [$perPage, $perPageOptions] = $this->perPageAndOptions($request, 25);

        $query = $this->filteredQuery($request);

        if ($sort === 'item_name') {
            $query->join('items', 'items.id', '=', 'open_stocks.item_id')
                ->orderBy('items.name', $direction)
                ->select('open_stocks.*');
        } elseif ($sort === 'outlet_code') {
            $query->join('outlets', 'outlets.id', '=', 'open_stocks.outlet_id')
                ->orderBy('outlets.code', $direction)
                ->select('open_stocks.*');
        } elseif ($sort === 'department_name') {
            $query->leftJoin('departments', 'departments.id', '=', 'open_stocks.department_id')
                ->orderBy('departments.name', $direction)
                ->select('open_stocks.*');
        } elseif ($sort === 'date') {
            $query->orderBy('open_stocks.business_date', $direction);
        } else {
            $query->orderBy('open_stocks.'.$sort, $direction);
        }

        $openStocks = $query
            ->orderBy('open_stocks.id', 'desc')
            ->paginate($perPage)
            ->withQueryString();

        $canFilterOutlet = ! $request->user()->outlet_id;

        return view('operations.open-stocks.index', [
            'openStocks' => $openStocks,
            'sort' => $sort,
            'direction' => $direction,
            'perPage' => $perPage,
            'perPageOptions' => $perPageOptions,
            'canFilterOutlet' => $canFilterOutlet,
            'filterOutlets' => $canFilterOutlet ? $this->filterOutlets($tenantId) : collect(),
            'filterBrands' => $canFilterOutlet ? $this->filterBrands($tenantId) : collect(),
            'filterDepartments' => $this->filterDepartments($tenantId),
        ]);
    }

    public function export(Request $request): BinaryFileResponse
    {
        $rows = $this->filteredQuery($request)
            ->orderBy('open_stocks.business_date', 'desc')
            ->orderBy('open_stocks.id', 'desc')
            ->get();

        return Excel::download(
            new OpenStockExport($rows, $request->user()->can('view_stock_value')),
            'OpenStock-'.now()->format('Ymd-His').'.xlsx'
        );
    }

    private function filteredQuery(Request $request): Builder
    {
        $tenantId = $request->user()->tenant_id;
        $userOutletId = $request->user()->outlet_id;

        $query = OpenStock::query()
            ->with(['outlet', 'department', 'item.baseUnit', 'item.inventoryUnit', 'item.purchaseUnit', 'unit', 'postedBy', 'createdBy'])
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->when($userOutletId, fn ($q) => $q->where('outlet_id', $userOutletId));

        if (! $userOutletId && $request->filled('outlet_id')) {
            $query->where('open_stocks.outlet_id', $request->integer('outlet_id'));
        }

        if (! $userOutletId && $request->filled('brand_id')) {
            $brandId = $request->integer('brand_id');
            $query->whereHas('outlet', fn ($q) => $q->where('brand_id', $brandId));
        }

        if ($request->filled('department_id')) {
            $query->where('open_stocks.department_id', $request->integer('department_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->upper()->toString());
        }

        if ($request->filled('date')) {
            $query->whereDate('business_date', $request->input('date'));
        }

        if ($request->filled('q')) {
            $search = $request->string('q')->toString();
            $query->whereHas('item', fn ($q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('canonical_sku', 'like', "%{$search}%")
            );
        }

        return $query;
    }

    /**
     * @return \Illuminate\Support\Collection<int, Outlet>
     */
    private function filterOutlets(?int $tenantId): \Illuminate\Support\Collection
    {
        return Outlet::query()
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->orderBy('name')
            ->get();
    }

    /**
     * @return \Illuminate\Support\Collection<int, Brand>
     */
    private function filterBrands(?int $tenantId): \Illuminate\Support\Collection
    {
        return Brand::query()
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->orderBy('name')
            ->get();
    }

    /**
     * @return \Illuminate\Support\Collection<int, Department>
     */
    private function filterDepartments(?int $tenantId): \Illuminate\Support\Collection
    {
        return Department::query()
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->where('status', 'ACTIVE')
            ->orderBy('name')
            ->get();
    }

    public function create(Request $request): View
    {
        return view('operations.open-stocks.create', $this->formData($request->user()));
    }

    public function store(StoreBulkOpenStockRequest $request): JsonResponse|RedirectResponse
    {
        $created = $this->openStockService->createBatchDraft($request->payload(), (int) $request->user()->id);
        $count   = $created->count();

        if ($request->expectsJson() || $request->isJson()) {
            return response()->json([
                'success' => true,
                'count' => $count,
                'redirect' => route('operations.open-stocks.index'),
                'message' => "{$count} Open Stock berhasil disimpan sebagai Draft.",
            ]);
        }

        return redirect()
            ->route('operations.open-stocks.index')
            ->with('success', "Berhasil menyimpan {$count} item Open Stock sebagai Draft.");
    }

    public function show(Request $request, OpenStock $openStock): View
    {
        $openStock->load(['outlet', 'item.inventoryUnit', 'item.baseUnit', 'unit', 'createdBy', 'postedBy', 'voidedBy']);

        $userOutletId = $request->user()->outlet_id;
        abort_if($userOutletId && (int) $openStock->outlet_id !== (int) $userOutletId, 403);

        return view('operations.open-stocks.show', [
            'openStock' => $openStock,
        ]);
    }

    public function edit(Request $request, OpenStock $openStock): View
    {
        abort_if($openStock->status !== OpenStock::STATUS_DRAFT, 403);

        return view('operations.open-stocks.edit', array_merge($this->formData($request->user()), [
            'openStock' => $openStock->load(['item', 'outlet']),
        ]));
    }

    public function update(UpdateOpenStockRequest $request, OpenStock $openStock): RedirectResponse
    {
        $openStock = $this->openStockService->updateDraft($openStock, $request->payload());

        return redirect()
            ->route('operations.open-stocks.show', $openStock)
            ->with('success', 'Open Stock draft berhasil diperbarui.');
    }

    public function destroy(OpenStock $openStock): RedirectResponse
    {
        $this->openStockService->deleteDraft($openStock);

        return redirect()
            ->route('operations.open-stocks.index')
            ->with('success', 'Open Stock draft berhasil dihapus.');
    }

    public function post(PostOpenStockRequest $request, OpenStock $openStock): RedirectResponse
    {
        $openStock = $this->openStockService->post($openStock, $request->user());

        return redirect()
            ->route('operations.open-stocks.show', $openStock)
            ->with('success', 'Open Stock berhasil diposting ke ledger stok.');
    }

    public function bulkPost(BulkPostOpenStockRequest $request): RedirectResponse
    {
        $tenantId = $request->user()->tenant_id;
        $ids = OpenStock::query()
            ->whereIn('id', $request->input('ids', []))
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->when($request->user()->outlet_id, fn ($q) => $q->where('outlet_id', $request->user()->outlet_id))
            ->where('status', OpenStock::STATUS_DRAFT)
            ->pluck('id')
            ->all();

        $result = $this->openStockService->bulkPost($ids, $request->user());

        $message = "{$result['posted']} Open Stock berhasil diposting ke ledger stok.";

        if ($result['failed'] === 0) {
            return redirect()
                ->route('operations.open-stocks.index')
                ->with('success', $message);
        }

        $shown = array_slice($result['errors'], 0, 5);
        $message .= " {$result['failed']} baris gagal: ".implode(' | ', $shown);

        if (count($result['errors']) > count($shown)) {
            $message .= ' (dan '.(count($result['errors']) - count($shown)).' lainnya)';
        }

        return redirect()
            ->route('operations.open-stocks.index')
            ->with('error', $message);
    }

    public function void(VoidOpenStockRequest $request, OpenStock $openStock): RedirectResponse
    {
        $this->openStockService->void(
            $openStock,
            $request->user(),
            $request->string('reason')->toString()
        );

        return redirect()
            ->route('operations.open-stocks.show', $openStock)
            ->with('success', 'Open Stock berhasil di-void. Entry VOID_REVERSAL telah dibuat di ledger.');
    }

    public function itemSearch(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $q        = $request->string('q')->toString();

        $items = Item::query()
            ->with(['inventoryUnit', 'baseUnit', 'purchaseUnit'])
            ->when($tenantId, fn ($query) => $query->where('tenant_id', $tenantId))
            ->where('is_active', true)
            ->where(fn ($query) => $query
                ->where('name', 'like', "%{$q}%")
                ->orWhere('canonical_sku', 'like', "%{$q}%")
            )
            ->orderBy('name')
            ->limit(20)
            ->get();

        return response()->json($items->map(fn (Item $item): array => [
            'id'             => $item->id,
            'name'           => $item->name,
            'sku'            => $item->canonical_sku,
            'canonical_sku'  => $item->canonical_sku,
            'base_unit'      => $item->baseUnit?->abbreviation ?? $item->baseUnit?->code ?? 'base',
            'base_unit_id'   => $item->base_unit_id,
            'inventory_unit' => $item->inventoryUnit?->abbreviation ?? $item->inventoryUnit?->code ?? $item->baseUnit?->abbreviation ?? $item->baseUnit?->code,
            'inventory_unit_id' => $item->inventory_unit_id ?? $item->base_unit_id,
            'purchase_unit'  => $item->purchaseUnit?->abbreviation ?? $item->purchaseUnit?->code ?? $item->inventoryUnit?->abbreviation ?? $item->inventoryUnit?->code,
            'purchase_unit_id' => $item->purchase_unit_id ?? $item->inventory_unit_id ?? $item->base_unit_id,
            'inventory_ratio'=> (float) ($item->inventory_ratio ?? 1),
            'purchase_ratio' => (float) ($item->purchase_ratio ?? $item->inventory_ratio ?? 1),
        ]));
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(User $user): array
    {
        $tenantId = $user->tenant_id;

        return [
            'outlets' => Outlet::query()
                ->when($tenantId, fn ($query) => $query->where('tenant_id', $tenantId))
                ->where('status', 'ACTIVE')
                ->when($user->outlet_id, fn ($q) => $q->where('id', $user->outlet_id))
                ->orderBy('name')
                ->get(),
            'items' => Item::query()
                ->with(['inventoryUnit', 'baseUnit', 'purchaseUnit'])
                ->when($tenantId, fn ($query) => $query->where('tenant_id', $tenantId))
                ->where('is_active', true)
                ->orderBy('name')
                ->get(),
            'departments' => Department::query()
                ->when($tenantId, fn ($query) => $query->where('tenant_id', $tenantId))
                ->where('status', 'ACTIVE')
                ->orderBy('name')
                ->get(),
            'targetOptions' => OpenStock::targetOptions(),
        ];
    }
}
