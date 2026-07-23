<?php

namespace App\Http\Controllers\Operations;

use App\Http\Controllers\Controller;
use App\Modules\Core\Models\Outlet;
use App\Modules\Inventory\Models\ItemCategory;
use App\Modules\Operations\Models\OpnameItem;
use App\Modules\Operations\Models\OpnameSession;
use App\Modules\Stock\Models\StockMutation;
use App\Services\OpnameService;
use App\Support\Decimal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class OpnameController extends Controller
{
    public function __construct(private readonly OpnameService $opnameService)
    {
    }

    public function index(Request $request): View
    {
        $tenantId = $this->tenantId($request);

        $sessions = OpnameSession::query()
            ->where('tenant_id', $tenantId)
            ->with(['outlet', 'createdBy', 'approvedBy'])
            ->withCount('items')
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->upper()->toString()))
            ->when($request->filled('date'), fn ($query) => $query->whereDate('opname_date', $request->date('date')))
            ->latest('opname_date')
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('operations.opname.index', [
            'sessions' => $sessions,
        ]);
    }

    public function create(Request $request): View
    {
        $tenantId = $this->tenantId($request);
        $outletId = (int) ($request->user()->outlet_id ?: Outlet::query()->where('tenant_id', $tenantId)->value('id'));

        return view('operations.opname.create', [
            'outlets' => Outlet::query()
                ->where('tenant_id', $tenantId)
                ->where('status', 'ACTIVE')
                ->orderBy('name')
                ->get(),
            'defaultOutletId' => $outletId,
            'dailyItemCount' => $outletId ? $this->opnameService->countDailyItems($tenantId, $outletId) : 0,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $tenantId = $this->tenantId($request);
        $validated = $request->validate([
            'outlet_id' => [
                'required',
                'integer',
                Rule::exists('outlets', 'id')->where('tenant_id', $tenantId),
            ],
            'opname_date' => ['required', 'date'],
            'shift' => ['nullable', Rule::in(['PAGI', 'SORE', 'MALAM'])],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $session = $this->opnameService->startSession(array_merge($validated, [
            'tenant_id' => $tenantId,
            'type' => OpnameSession::TYPE_DAILY,
        ]), (int) $request->user()->id);

        return redirect()
            ->route('operations.opname.show', $session)
            ->with('success', 'Sesi opname berhasil dibuat.');
    }

    public function show(Request $request, OpnameSession $session): View
    {
        $session->load([
            'outlet',
            'createdBy',
            'submittedBy',
            'approvedBy',
        ]);

        $search = $request->string('q')->toString();
        $categoryId = $request->string('category_id')->toString();
        $allowedPerPage = ['20', '50', '100', 'all'];
        $perPage = in_array($request->string('per_page')->toString(), $allowedPerPage, true)
            ? $request->string('per_page')->toString()
            : '20';

        $roleNames = $request->user()->getRoleNames();
        $roleFilter = null;

        if ($roleNames->contains('STAFF_BAR')) {
            $roleFilter = 'BAR';
        } elseif ($roleNames->contains('STAFF_KITCHEN')) {
            $roleFilter = 'KITCHEN';
        } elseif ($roleNames->contains('STAFF_GUDANG')) {
            $roleFilter = 'GUDANG';
        }

        $query = OpnameItem::query()
            ->where('opname_session_id', $session->id)
            ->with([
                'item.inventoryUnit',
                'item.baseUnit',
                'item.category',
                'item.jenis',
                'item.primaryDepartment',
                'department',
                'unit',
            ]);

        if ($roleFilter !== null) {
            $query->where(function ($q) use ($roleFilter): void {
                $q->whereHas('item.primaryDepartment', fn ($departmentQuery) => $departmentQuery->where('name', 'like', "%{$roleFilter}%"))
                    ->orWhereHas('department', fn ($departmentQuery) => $departmentQuery->where('name', 'like', "%{$roleFilter}%"))
                    ->orWhere(function ($fallbackQuery): void {
                        $fallbackQuery
                            ->whereNull('department_id')
                            ->whereHas('item', fn ($itemQuery) => $itemQuery->whereNull('primary_department_id'));
                    });
            });
        }

        if ($search !== '') {
            $query->whereHas('item', fn ($q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('canonical_sku', 'like', "%{$search}%")
            );
        }

        if ($categoryId !== '') {
            $query->whereHas('item', fn ($q) => $q->where('item_category_id', (int) $categoryId));
        }

        if ($perPage === 'all') {
            $items = $query->orderBy('id')->get();
            $paginator = null;
        } else {
            $paginator = $query->orderBy('id')->paginate((int) $perPage)->withQueryString();
            $items = $paginator->getCollection();
        }

        $total = OpnameItem::query()
            ->where('opname_session_id', $session->id)
            ->count();
        $counted = OpnameItem::query()
            ->where('opname_session_id', $session->id)
            ->where('is_counted', true)
            ->count();

        // Batch-load current stock balances — sum semua target (DAILY + WAREHOUSE)
        $balanceMap = DB::table('stock_balances')
            ->where('outlet_id', $session->outlet_id)
            ->whereIn('item_id', $items->pluck('item_id')->unique()->values())
            ->groupBy('item_id')
            ->selectRaw('item_id, SUM(qty_on_hand) as qty_on_hand')
            ->pluck('qty_on_hand', 'item_id');

        // Rasio default berdasarkan pasangan unit (dipakai jika inventory_ratio null dan unit_conversions kosong)
        $knownRatios = [
            'kg-gr' => 1000, 'kg-mg' => 1_000_000,
            'l-ml'  => 1000, 'l-cl'  => 100, 'l-dl' => 10, 'ltr-ml' => 1000,
        ];

        // Batch-load unit conversion fallback for items whose inventory_ratio is null/0
        $nullRatioItems = $items->filter(fn ($i) => ! ((float) ($i->item?->inventory_ratio)));
        $conversionRatioMap = collect();
        if ($nullRatioItems->isNotEmpty()) {
            $invUnitIds  = $nullRatioItems->map(fn ($i) => $i->item?->inventory_unit_id)->filter()->unique()->values();
            $baseUnitIds = $nullRatioItems->map(fn ($i) => $i->item?->base_unit_id)->filter()->unique()->values();
            $itemIds     = $nullRatioItems->pluck('item_id')->values();
            $convRows    = DB::table('unit_conversions')
                ->where(fn ($q) => $q->whereIn('item_id', $itemIds)->orWhereNull('item_id'))
                ->whereIn('from_unit_id', $invUnitIds)
                ->whereIn('to_unit_id', $baseUnitIds)
                ->get(['item_id', 'from_unit_id', 'to_unit_id', 'multiply_rate']);
            foreach ($nullRatioItems as $opItem) {
                $itm = $opItem->item;
                if (! $itm) {
                    continue;
                }
                $specific = $convRows->first(fn ($c) => $c->item_id == $itm->id
                    && $c->from_unit_id == $itm->inventory_unit_id
                    && $c->to_unit_id == $itm->base_unit_id);
                $global   = $convRows->first(fn ($c) => is_null($c->item_id)
                    && $c->from_unit_id == $itm->inventory_unit_id
                    && $c->to_unit_id == $itm->base_unit_id);
                $conv     = $specific ?? $global;
                if ($conv) {
                    $fallback = (float) $conv->multiply_rate;
                } else {
                    $invAbbr  = strtolower((string) ($itm->inventoryUnit?->abbreviation ?? ''));
                    $basAbbr  = strtolower((string) ($itm->baseUnit?->abbreviation ?? ''));
                    $pairKey  = $invAbbr === $basAbbr ? 'same' : "{$invAbbr}-{$basAbbr}";
                    $fallback = $pairKey === 'same' ? 1.0 : (float) ($knownRatios[$pairKey] ?? 1.0);
                }
                $conversionRatioMap[(int) $opItem->item_id] = $fallback;
            }
        }

        $items->each(function (OpnameItem $opnameItem) use ($balanceMap, $conversionRatioMap): void {
            $opnameItem->stok_sistem = (float) ($balanceMap->get($opnameItem->item_id) ?? 0);
            $ratio                   = (float) ($opnameItem->item?->inventory_ratio ?: 0);
            $opnameItem->inv_ratio   = $ratio > 0 ? $ratio : (float) ($conversionRatioMap->get((int) $opnameItem->item_id) ?? 1.0);
        });

        $categories = ItemCategory::query()
            ->where('tenant_id', $this->tenantId($request))
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('operations.opname.show', [
            'session' => $session,
            'items' => $items,
            'paginator' => $paginator,
            'search' => $search,
            'categoryId' => $categoryId,
            'perPage' => $perPage,
            'categories' => $categories,
            'roleFilter' => $roleFilter,
            'counted' => $counted,
            'total' => $total,
        ]);
    }

    public function updateItem(Request $request, OpnameSession $session, OpnameItem $item): JsonResponse
    {
        abort_unless((int) $item->opname_session_id === (int) $session->id, 404);

        $validated = $request->validate([
            'qty_whole' => ['nullable', Decimal::validationRule(6)],
            'qty_loose' => ['nullable', Decimal::validationRule(6)],
        ]);

        $updated = $this->opnameService->updateItem(
            $item,
            $validated['qty_whole'] ?? 0,
            $validated['qty_loose'] ?? 0
        );

        $session->refresh()->loadCount(['items', 'items as counted_items_count' => fn ($query) => $query->where('is_counted', true)]);

        return response()->json([
            'success' => true,
            'variance' => (string) $updated->variance,
            'variance_value' => (string) $updated->variance_value,
            'physical_qty_base' => (string) $updated->physical_qty_base,
            'counted' => $session->counted_items_count,
            'total' => $session->items_count,
        ]);
    }

    public function submit(Request $request, OpnameSession $session): RedirectResponse
    {
        $updated = $this->opnameService->submit($session, (int) $request->user()->id);

        return redirect()
            ->route('operations.opname.show', $updated)
            ->with('success', 'Sesi opname berhasil disubmit untuk approval.');
    }

    public function approve(Request $request, OpnameSession $session): RedirectResponse
    {
        try {
            $updated = $this->opnameService->approve($session, (int) $request->user()->id);
        } catch (ValidationException $exception) {
            throw $exception;
        }

        return redirect()
            ->route('operations.opname.show', $updated)
            ->with('success', 'Sesi opname berhasil diproses ke stock ledger.');
    }

    private function tenantId(Request $request): int
    {
        $tenantId = $request->user()?->tenant_id;

        abort_unless($tenantId, 403, 'Tenant belum terpasang pada user.');

        return (int) $tenantId;
    }
}
