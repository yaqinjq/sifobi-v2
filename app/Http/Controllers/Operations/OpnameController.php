<?php

namespace App\Http\Controllers\Operations;

use App\Http\Controllers\Concerns\HasPerPageSelector;
use App\Http\Controllers\Controller;
use App\Http\Requests\Operations\BulkApproveOpnameRequest;
use App\Http\Requests\Operations\BulkSubmitOpnameRequest;
use App\Modules\Core\Models\Brand;
use App\Modules\Core\Models\Department;
use App\Modules\Core\Models\Outlet;
use App\Modules\Inventory\Models\ItemCategory;
use App\Modules\Operations\Models\OpnameItem;
use App\Modules\Operations\Models\OpnameSession;
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
    use HasPerPageSelector;

    public function __construct(private readonly OpnameService $opnameService)
    {
    }

    public function index(Request $request): View
    {
        $tenantId = $this->tenantId($request);
        $userOutletId = $request->user()->outlet_id;
        [$perPage, $perPageOptions] = $this->perPageAndOptions($request, 20);

        $sessions = OpnameSession::query()
            ->where('tenant_id', $tenantId)
            ->when($userOutletId, fn ($q) => $q->where('outlet_id', $userOutletId))
            ->with(['outlet', 'department', 'createdBy', 'approvedBy'])
            ->withCount('items')
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->upper()->toString()))
            ->when($request->filled('date'), fn ($query) => $query->whereDate('opname_date', $request->date('date')))
            ->when(! $userOutletId && $request->filled('outlet_id'), fn ($query) => $query->where('outlet_id', $request->integer('outlet_id')))
            ->when(! $userOutletId && $request->filled('brand_id'), fn ($query) => $query->whereHas(
                'outlet',
                fn ($q) => $q->where('brand_id', $request->integer('brand_id'))
            ))
            ->when($request->filled('department_id'), fn ($query) => $query->where('department_id', $request->integer('department_id')))
            ->latest('opname_date')
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();

        $canFilterOutlet = ! $userOutletId;

        return view('operations.opname.index', [
            'sessions' => $sessions,
            'perPage' => $perPage,
            'perPageOptions' => $perPageOptions,
            'canFilterOutlet' => $canFilterOutlet,
            'filterOutlets' => $canFilterOutlet ? Outlet::query()->where('tenant_id', $tenantId)->orderBy('name')->get() : collect(),
            'filterBrands' => $canFilterOutlet ? Brand::query()->where('tenant_id', $tenantId)->orderBy('name')->get() : collect(),
            'filterDepartments' => Department::query()->where('tenant_id', $tenantId)->where('status', 'ACTIVE')->orderBy('name')->get(),
        ]);
    }

    public function create(Request $request): View
    {
        $tenantId = $this->tenantId($request);
        $user = $request->user();
        $outletId = (int) ($user->outlet_id ?: Outlet::query()->where('tenant_id', $tenantId)->value('id'));
        $departmentId = $user->department_id ? (int) $user->department_id : null;

        return view('operations.opname.create', [
            'outlets' => Outlet::query()
                ->where('tenant_id', $tenantId)
                ->where('status', 'ACTIVE')
                ->when($user->outlet_id, fn ($q) => $q->where('id', $user->outlet_id))
                ->orderBy('name')
                ->get(),
            'departments' => Department::query()
                ->where('tenant_id', $tenantId)
                ->where('status', 'ACTIVE')
                ->when($user->department_id, fn ($q) => $q->where('id', $user->department_id))
                ->orderBy('name')
                ->get(),
            'defaultOutletId' => $outletId,
            'defaultDepartmentId' => $departmentId,
            'canChangeOutlet' => ! $user->outlet_id,
            'canChangeDepartment' => ! $user->department_id,
            'dailyItemCount' => ($outletId && $departmentId) ? $this->opnameService->countDailyItems($tenantId, $outletId, $departmentId) : null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $tenantId = $this->tenantId($request);
        $user = $request->user();

        $rules = [
            'shift' => ['nullable', Rule::in(['PAGI', 'SORE', 'MALAM'])],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];

        if (! $user->outlet_id) {
            $rules['outlet_id'] = ['required', 'integer', Rule::exists('outlets', 'id')->where('tenant_id', $tenantId)];
        }

        if (! $user->department_id) {
            $rules['department_id'] = ['required', 'integer', Rule::exists('departments', 'id')->where('tenant_id', $tenantId)];
        }

        $validated = $request->validate($rules);

        $session = $this->opnameService->startSession([
            'tenant_id' => $tenantId,
            'outlet_id' => $user->outlet_id ?: (int) $validated['outlet_id'],
            'department_id' => $user->department_id ?: (int) $validated['department_id'],
            'opname_date' => now()->toDateString(),
            'shift' => $validated['shift'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'type' => OpnameSession::TYPE_DAILY,
        ], (int) $user->id);

        return redirect()
            ->route('operations.opname.show', $session)
            ->with('success', 'Sesi opname berhasil dibuat.');
    }

    public function createHistorical(Request $request): View
    {
        $tenantId = $this->tenantId($request);
        $user = $request->user();

        return view('operations.opname.create-historical', [
            'outlets' => Outlet::query()
                ->where('tenant_id', $tenantId)
                ->where('status', 'ACTIVE')
                ->when($user->outlet_id, fn ($q) => $q->where('id', $user->outlet_id))
                ->orderBy('name')
                ->get(),
            'departments' => Department::query()
                ->where('tenant_id', $tenantId)
                ->where('status', 'ACTIVE')
                ->when($user->department_id, fn ($q) => $q->where('id', $user->department_id))
                ->orderBy('name')
                ->get(),
            'defaultOutletId' => (int) ($user->outlet_id ?: Outlet::query()->where('tenant_id', $tenantId)->value('id')),
            'defaultDepartmentId' => $user->department_id ? (int) $user->department_id : null,
            'canChangeOutlet' => ! $user->outlet_id,
            'canChangeDepartment' => ! $user->department_id,
        ]);
    }

    public function storeHistorical(Request $request): RedirectResponse
    {
        $tenantId = $this->tenantId($request);
        $user = $request->user();

        $rules = [
            'opname_date' => ['required', 'date', 'before_or_equal:today'],
            'shift' => ['nullable', Rule::in(['PAGI', 'SORE', 'MALAM'])],
            'notes' => ['required', 'string', 'min:5', 'max:2000'],
        ];

        if (! $user->outlet_id) {
            $rules['outlet_id'] = ['required', 'integer', Rule::exists('outlets', 'id')->where('tenant_id', $tenantId)];
        }

        if (! $user->department_id) {
            $rules['department_id'] = ['required', 'integer', Rule::exists('departments', 'id')->where('tenant_id', $tenantId)];
        }

        $validated = $request->validate($rules);

        $session = $this->opnameService->startSession([
            'tenant_id' => $tenantId,
            'outlet_id' => $user->outlet_id ?: (int) $validated['outlet_id'],
            'department_id' => $user->department_id ?: (int) $validated['department_id'],
            'opname_date' => $validated['opname_date'],
            'shift' => $validated['shift'] ?? null,
            'notes' => $validated['notes'],
            'type' => OpnameSession::TYPE_DAILY,
        ], (int) $user->id);

        return redirect()
            ->route('operations.opname.show', $session)
            ->with('success', 'Sesi opname historis berhasil dibuat.');
    }

    public function bulkSubmit(BulkSubmitOpnameRequest $request): RedirectResponse
    {
        $tenantId = $this->tenantId($request);
        $ids = OpnameSession::query()
            ->whereIn('id', $request->input('ids', []))
            ->where('tenant_id', $tenantId)
            ->when($request->user()->outlet_id, fn ($q) => $q->where('outlet_id', $request->user()->outlet_id))
            ->where('status', OpnameSession::STATUS_DRAFT)
            ->pluck('id')
            ->all();

        $result = $this->opnameService->bulkSubmit($ids, (int) $request->user()->id);

        return $this->bulkRedirect($result, "{$result['processed']} sesi opname berhasil disubmit untuk approval.");
    }

    public function bulkApprove(BulkApproveOpnameRequest $request): RedirectResponse
    {
        $tenantId = $this->tenantId($request);
        $ids = OpnameSession::query()
            ->whereIn('id', $request->input('ids', []))
            ->where('tenant_id', $tenantId)
            ->when($request->user()->outlet_id, fn ($q) => $q->where('outlet_id', $request->user()->outlet_id))
            ->where('status', OpnameSession::STATUS_SUBMITTED)
            ->pluck('id')
            ->all();

        $result = $this->opnameService->bulkApprove($ids, (int) $request->user()->id);

        return $this->bulkRedirect($result, "{$result['processed']} sesi opname berhasil diproses ke stock ledger.");
    }

    /**
     * @param  array{processed: int, failed: int, errors: list<string>}  $result
     */
    private function bulkRedirect(array $result, string $message): RedirectResponse
    {
        if ($result['failed'] === 0) {
            return redirect()->route('operations.opname.index')->with('success', $message);
        }

        $shown = array_slice($result['errors'], 0, 5);
        $message .= " {$result['failed']} gagal: ".implode(' | ', $shown);

        if (count($result['errors']) > count($shown)) {
            $message .= ' (dan '.(count($result['errors']) - count($shown)).' lainnya)';
        }

        return redirect()->route('operations.opname.index')->with('error', $message);
    }

    public function show(Request $request, OpnameSession $session): View
    {
        $session->load([
            'outlet',
            'department',
            'createdBy',
            'submittedBy',
            'approvedBy',
        ]);

        $userOutletId = $request->user()->outlet_id;
        abort_if($userOutletId && (int) $session->outlet_id !== (int) $userOutletId, 403);

        $search = $request->string('q')->toString();
        $categoryId = $request->string('category_id')->toString();
        $sortMode = in_array($request->string('sort_mode')->toString(), ['az', 'form'], true)
            ? $request->string('sort_mode')->toString()
            : 'az';
        $viewMode = in_array($request->string('view_mode')->toString(), ['card', 'list', 'category', 'zoom'], true)
            ? $request->string('view_mode')->toString()
            : 'card';
        $allowedPerPage = ['20', '50', '100', 'all'];
        $perPage = in_array($request->string('per_page')->toString(), $allowedPerPage, true)
            ? $request->string('per_page')->toString()
            : '20';

        // Mode "Sesuai Form", "Per Kategori", dan "Zoom" butuh mengurutkan/
        // mengelompokkan lintas SELURUH item sesi (bukan cuma 1 halaman) --
        // makanya mode-mode ini otomatis menampilkan semua item tanpa
        // pagination.
        if ($sortMode === 'form' || in_array($viewMode, ['category', 'zoom'], true)) {
            $perPage = 'all';
        }

        // null = tidak dibatasi departemen (mis. PIC_OUTLET/MANAGER_AREA/ADMIN
        // yang memang harus lihat semua departemen outlet).
        $userDepartmentId = $request->user()->department_id;
        $departmentLabel = $userDepartmentId ? Department::find($userDepartmentId)?->name : null;

        $query = OpnameItem::query()
            ->where('opname_session_id', $session->id)
            ->when($userDepartmentId, fn ($q) => $q->where(
                fn ($dq) => $dq->where('department_id', $userDepartmentId)->orWhereNull('department_id')
            ))
            ->with([
                'item.inventoryUnit',
                'item.baseUnit',
                'item.category.parent',
                'item.jenis',
                'item.primaryDepartment',
                'item.departments',
                'department',
                'unit',
            ]);

        if ($search !== '') {
            $query->whereHas('item', fn ($q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('canonical_sku', 'like', "%{$search}%")
            );
        }

        if ($categoryId !== '') {
            // Filter kategori bisa berupa kategori utama (ikut sub kategori di
            // dalamnya) atau langsung sub kategori -- item sebenarnya di-tag
            // di level sub kategori, sedangkan dropdown filter menampilkan
            // keduanya (lihat categoryOptionsForSession()).
            $categoryIds = ItemCategory::query()
                ->where(fn ($q) => $q->where('id', (int) $categoryId)->orWhere('parent_id', (int) $categoryId))
                ->pluck('id');
            $query->whereHas('item', fn ($q) => $q->whereIn('item_category_id', $categoryIds));
        }

        if ($perPage === 'all') {
            $items = $query->orderBy('id')->get();
            $paginator = null;
        } else {
            $paginator = $query->orderBy('id')->paginate((int) $perPage)->withQueryString();
            $items = $paginator->getCollection();
        }

        if ($sortMode === 'form') {
            $items = $items->sortBy(fn (OpnameItem $opnameItem): string => $this->formSortKey($opnameItem))->values();
        }

        $groupedItems = null;

        if ($viewMode === 'category') {
            $groupedItems = $items
                ->sortBy(fn (OpnameItem $opnameItem): string => $this->formSortKey($opnameItem))
                ->values()
                ->groupBy(fn (OpnameItem $opnameItem): string => $opnameItem->item?->category?->parent?->name
                    ?? $opnameItem->item?->category?->name
                    ?? 'Tanpa Kategori');
        }

        $departmentScope = fn ($q) => $q->where(
            fn ($dq) => $dq->where('department_id', $userDepartmentId)->orWhereNull('department_id')
        );

        $total = OpnameItem::query()
            ->where('opname_session_id', $session->id)
            ->when($userDepartmentId, $departmentScope)
            ->count();
        $counted = OpnameItem::query()
            ->where('opname_session_id', $session->id)
            ->where('is_counted', true)
            ->when($userDepartmentId, $departmentScope)
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

        // Item yang muncul lebih dari satu baris dalam sesi ini (shared antar departemen)
        $sharedItemIds = OpnameItem::query()
            ->where('opname_session_id', $session->id)
            ->selectRaw('item_id, COUNT(*) as row_count')
            ->groupBy('item_id')
            ->having('row_count', '>', 1)
            ->pluck('item_id')
            ->flip()
            ->all();

        $categories = $this->categoryOptionsForSession($request, $session);

        return view('operations.opname.show', [
            'session' => $session,
            'items' => $items,
            'paginator' => $paginator,
            'search' => $search,
            'categoryId' => $categoryId,
            'sortMode' => $sortMode,
            'viewMode' => $viewMode,
            'groupedItems' => $groupedItems,
            'perPage' => $perPage,
            'categories' => $categories,
            'roleFilter' => $departmentLabel,
            'counted' => $counted,
            'total' => $total,
            'sharedItemIds' => $sharedItemIds,
        ]);
    }

    /**
     * Kunci urutan "Sesuai Form": kategori utama (sort_order+nama) lalu sub
     * kategori (sort_order), lalu nama item -- dipakai oleh sort_mode=form
     * DAN view_mode=category (supaya pengelompokan tetap rapi tanpa terikat
     * pada sort_mode yang sedang aktif).
     */
    private function formSortKey(OpnameItem $opnameItem): string
    {
        $leaf = $opnameItem->item?->category;
        $parent = $leaf?->parent ?? $leaf;
        $childOrder = ($leaf && $leaf->parent_id) ? $leaf->sort_order : 0;

        return sprintf(
            '%05d|%s|%05d|%s',
            $parent?->sort_order ?? 0,
            $parent?->name ?? '',
            $childOrder,
            $opnameItem->item?->name ?? ''
        );
    }

    /**
     * Kategori & sub kategori untuk dropdown filter -- kalau sesi sudah
     * di-scope ke satu departemen (lihat Fase 2), hanya kategori yang
     * di-mapping ke departemen tsb (lewat Department::itemCategories(),
     * diisi via Settings > Mapping Departemen & Kategori) yang muncul,
     * bukan semua kategori master. Kalau sesi lintas-departemen (department
     * null, mis. opname historis lama), tampilkan semua kategori seperti
     * sebelumnya.
     *
     * @return \Illuminate\Support\Collection<int, ItemCategory>
     */
    private function categoryOptionsForSession(Request $request, OpnameSession $session): \Illuminate\Support\Collection
    {
        if ($session->department_id) {
            $topLevel = $session->department?->itemCategories()
                ->where('is_active', true)
                ->with(['children' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order')->orderBy('name')])
                ->orderBy('name')
                ->get() ?? collect();
        } else {
            $topLevel = ItemCategory::query()
                ->where('tenant_id', $this->tenantId($request))
                ->whereNull('parent_id')
                ->where('is_active', true)
                ->with(['children' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order')->orderBy('name')])
                ->orderBy('name')
                ->get();
        }

        return $topLevel->flatMap(fn (ItemCategory $category) => collect([$category])->merge($category->children));
    }

    public function updateItem(Request $request, OpnameSession $session, OpnameItem $item): JsonResponse
    {
        abort_unless((int) $item->opname_session_id === (int) $session->id, 404);

        $userDepartmentId = $request->user()->department_id;
        abort_unless(
            is_null($userDepartmentId)
                || is_null($item->department_id)
                || (int) $item->department_id === (int) $userDepartmentId,
            403,
            'Anda tidak berwenang mengubah item departemen lain.'
        );

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

    public function syncItem(Request $request, OpnameSession $session, OpnameItem $item): JsonResponse
    {
        abort_unless((int) $item->opname_session_id === (int) $session->id, 404);

        $userDepartmentId = $request->user()->department_id;
        abort_unless(
            is_null($userDepartmentId)
                || is_null($item->department_id)
                || (int) $item->department_id === (int) $userDepartmentId,
            403,
            'Anda tidak berwenang mengubah item departemen lain.'
        );

        $updated = $this->opnameService->syncItemBaseline($item);

        return response()->json([
            'success' => true,
            'system_qty_base' => (string) $updated->system_qty_base,
            'variance' => (string) $updated->variance,
            'variance_value' => (string) $updated->variance_value,
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
