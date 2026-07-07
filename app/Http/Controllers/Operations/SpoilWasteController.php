<?php

namespace App\Http\Controllers\Operations;

use App\Exceptions\InsufficientStockException;
use App\Http\Controllers\Controller;
use App\Modules\Core\Models\Department;
use App\Modules\Core\Models\Outlet;
use App\Modules\Inventory\Models\Item;
use App\Modules\Operations\Models\SpoilWaste;
use App\Modules\Stock\Models\StockBalance;
use App\Modules\Stock\Models\StockMutation;
use App\Services\SpoilWasteService;
use App\Support\Decimal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SpoilWasteController extends Controller
{
    public function __construct(private readonly SpoilWasteService $spoilWasteService)
    {
    }

    public function index(Request $request): View
    {
        $tenantId = $this->tenantId($request);

        $query = SpoilWaste::query()
            ->with(['outlet', 'department', 'item', 'unit', 'createdBy'])
            ->where('tenant_id', $tenantId)
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->upper()->toString()))
            ->when($request->input('range') === '7', fn ($q) => $q->whereDate('recorded_date', '>=', now()->subDays(7)->toDateString()))
            ->when($request->input('range') === '30', fn ($q) => $q->whereDate('recorded_date', '>=', now()->subDays(30)->toDateString()))
            ->when($request->input('range', 'today') === 'today', fn ($q) => $q->whereDate('recorded_date', now()->toDateString()))
            ->when($request->input('filter') === 'duplicate', fn ($q) => $q->where('is_duplicate_photo', true))
            ->when($request->filled('q'), function ($q) use ($request): void {
                $search = '%'.$request->string('q')->trim().'%';
                $q->whereHas('item', fn ($itemQuery) => $itemQuery
                    ->where('name', 'like', $search)
                    ->orWhere('canonical_sku', 'like', $search));
            })
            ->latest('recorded_at')
            ->paginate(20)
            ->withQueryString();

        return view('operations.spoil-wastes.index', [
            'spoilWastes' => $query,
            'duplicateCount' => SpoilWaste::query()
                ->where('tenant_id', $tenantId)
                ->where('is_duplicate_photo', true)
                ->where('status', SpoilWaste::STATUS_PENDING)
                ->count(),
        ]);
    }

    public function create(Request $request): View
    {
        return view('operations.spoil-wastes.create', $this->formData($request));
    }

    public function store(Request $request): RedirectResponse
    {
        $tenantId = $this->tenantId($request);
        $validated = $this->validated($request, $tenantId);

        try {
            $spoil = $this->spoilWasteService->record(array_merge($validated, [
                'tenant_id' => $tenantId,
                'photo_file' => $request->file('photo'),
                'device_info' => $request->userAgent(),
                'ip_address' => $request->ip(),
            ]), (int) $request->user()->id);
        } catch (InsufficientStockException $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('operations.spoil-wastes.show', $spoil)
            ->with('success', 'Spoil berhasil dicatat dan stok langsung berkurang.');
    }

    public function show(SpoilWaste $spoil): View
    {
        $spoil->load([
            'outlet',
            'department',
            'item.baseUnit',
            'unit',
            'createdBy',
            'approvedBy',
            'duplicateReference.createdBy',
            'mutation',
        ]);

        return view('operations.spoil-wastes.show', [
            'spoil' => $spoil,
        ]);
    }

    public function approve(Request $request, SpoilWaste $spoil): RedirectResponse
    {
        $validated = $request->validate([
            'approval_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $updated = $this->spoilWasteService->approve(
            $spoil,
            (int) $request->user()->id,
            $validated['approval_notes'] ?? ''
        );

        return redirect()
            ->route('operations.spoil-wastes.show', $updated)
            ->with('success', 'Spoil berhasil di-approve.');
    }

    public function reject(Request $request, SpoilWaste $spoil): RedirectResponse
    {
        $validated = $request->validate([
            'approval_notes' => ['required', 'string', 'max:1000'],
        ]);

        $updated = $this->spoilWasteService->reject(
            $spoil,
            (int) $request->user()->id,
            $validated['approval_notes']
        );

        return redirect()
            ->route('operations.spoil-wastes.show', $updated)
            ->with('warning', 'Spoil ditolak dan stok dikembalikan lewat VOID_REVERSAL.');
    }

    public function searchItems(Request $request): JsonResponse
    {
        $tenantId = $this->tenantId($request);
        $outletId = (int) ($request->input('outlet_id') ?: $request->user()->outlet_id);
        $search = $request->string('q')->trim()->toString();

        $items = Item::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->where('track_stock', true)
            ->with(['baseUnit', 'inventoryUnit'])
            ->when($search !== '', fn ($query) => $query->where(fn ($inner) => $inner
                ->where('name', 'like', "%{$search}%")
                ->orWhere('canonical_sku', 'like', "%{$search}%")))
            ->orderBy('name')
            ->limit(20)
            ->get();

        return response()->json($items->map(function (Item $item) use ($tenantId, $outletId): array {
            $balance = StockBalance::query()
                ->where('tenant_id', $tenantId)
                ->where('outlet_id', $outletId)
                ->where('item_id', $item->id)
                ->where('stock_target', StockMutation::TARGET_OUTLET_DAILY)
                ->first();

            return [
                'id' => $item->id,
                'name' => $item->name,
                'sku' => $item->canonical_sku,
                'base_unit_id' => $item->base_unit_id,
                'base_unit' => $item->baseUnit?->abbreviation ?? $item->baseUnit?->code ?? 'base',
                'inventory_unit_id' => $item->inventory_unit_id ?: $item->base_unit_id,
                'inventory_unit' => $item->inventoryUnit?->abbreviation ?? $item->inventoryUnit?->code ?? $item->baseUnit?->abbreviation ?? 'unit',
                'inventory_ratio' => (float) ($item->inventory_ratio ?: 1),
                'qty_on_hand' => (float) ($balance?->qty_on_hand ?? 0),
            ];
        }));
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(Request $request): array
    {
        $tenantId = $this->tenantId($request);

        return [
            'outlets' => Outlet::query()
                ->where('tenant_id', $tenantId)
                ->where('status', 'ACTIVE')
                ->orderBy('name')
                ->get(),
            'departments' => Department::query()
                ->where('tenant_id', $tenantId)
                ->where('status', 'ACTIVE')
                ->orderBy('name')
                ->get(),
            'reasonOptions' => [
                SpoilWaste::REASON_EXPIRED => 'Kadaluarsa',
                SpoilWaste::REASON_RUSAK => 'Rusak/Cacat',
                SpoilWaste::REASON_KESALAHAN_PRODUKSI => 'Kesalahan Produksi',
                SpoilWaste::REASON_TUMPAH => 'Tumpah',
                SpoilWaste::REASON_QUALITY_REJECT => 'Reject Kualitas',
                SpoilWaste::REASON_LAINNYA => 'Lainnya',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, int $tenantId): array
    {
        $validated = $request->validate([
            'outlet_id' => [
                'required',
                'integer',
                Rule::exists('outlets', 'id')->where('tenant_id', $tenantId),
            ],
            'department_id' => [
                'required',
                'integer',
                Rule::exists('departments', 'id')->where('tenant_id', $tenantId),
            ],
            'item_id' => [
                'required',
                'integer',
                Rule::exists('items', 'id')->where('tenant_id', $tenantId),
            ],
            'unit_id' => [
                'required',
                'integer',
                Rule::exists('units', 'id')->where('tenant_id', $tenantId),
            ],
            'qty' => ['required', Decimal::validationRule(6)],
            'recorded_date' => ['required', 'date'],
            'reason_category' => ['required', Rule::in([
                SpoilWaste::REASON_EXPIRED,
                SpoilWaste::REASON_RUSAK,
                SpoilWaste::REASON_KESALAHAN_PRODUKSI,
                SpoilWaste::REASON_TUMPAH,
                SpoilWaste::REASON_QUALITY_REJECT,
                SpoilWaste::REASON_LAINNYA,
            ])],
            'reason_detail' => ['nullable', 'string', 'max:2000'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        if (bccomp(Decimal::toFixed($validated['qty'], 6), '0.000000', 6) <= 0) {
            throw ValidationException::withMessages([
                'qty' => 'Qty spoil harus lebih dari 0.',
            ]);
        }

        return $validated;
    }

    private function tenantId(Request $request): int
    {
        $tenantId = $request->user()?->tenant_id;

        abort_unless($tenantId, 403, 'Tenant belum terpasang pada user.');

        return (int) $tenantId;
    }
}
