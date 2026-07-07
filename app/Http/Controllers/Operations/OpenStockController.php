<?php

namespace App\Http\Controllers\Operations;

use App\Http\Controllers\Controller;
use App\Http\Requests\Operations\PostOpenStockRequest;
use App\Http\Requests\Operations\StoreBulkOpenStockRequest;
use App\Http\Requests\Operations\UpdateOpenStockRequest;
use App\Http\Requests\Operations\VoidOpenStockRequest;
use App\Models\User;
use App\Modules\Core\Models\Department;
use App\Modules\Core\Models\Outlet;
use App\Modules\Inventory\Models\Item;
use App\Modules\Operations\Models\OpenStock;
use App\Services\OpenStockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OpenStockController extends Controller
{
    public function __construct(private readonly OpenStockService $openStockService)
    {
    }

    public function index(Request $request): View
    {
        $tenantId = $request->user()->tenant_id;

        $query = OpenStock::query()
            ->with(['outlet', 'item.inventoryUnit', 'unit', 'postedBy', 'createdBy'])
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId));

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

        $openStocks = $query->latest('business_date')->latest()->limit(100)->get();

        return view('operations.open-stocks.index', [
            'openStocks' => $openStocks,
        ]);
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

    public function show(OpenStock $openStock): View
    {
        $openStock->load(['outlet', 'item.inventoryUnit', 'item.baseUnit', 'unit', 'createdBy', 'postedBy', 'voidedBy']);

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
