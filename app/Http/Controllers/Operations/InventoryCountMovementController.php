<?php

namespace App\Http\Controllers\Operations;

use App\Http\Controllers\Concerns\EnforcesInventorySimpleScope;
use App\Http\Controllers\Controller;
use App\Modules\Core\Models\Department;
use App\Modules\Core\Models\Outlet;
use App\Modules\Inventory\Models\Item;
use App\Modules\Operations\Models\InventoryCountTemplate;
use App\Services\InventoryCountMovementService;
use App\Support\Decimal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Rekap Mingguan -- saldo mandiri Inventory Simple (IN/OUT + saldo
 * berjalan), SENGAJA TERISOLASI dari stock_balances/stock_mutations
 * (ledger resmi SIFOBI). Lihat InventoryCountMovementService dan
 * komentar migration inventory_count_movements untuk alasan isolasi ini.
 * Item yang tampil di sini bersumber dari InventoryCountTemplate yang
 * sama dipakai form hitung fisik harian -- bukan daftar item terpisah.
 */
class InventoryCountMovementController extends Controller
{
    use EnforcesInventorySimpleScope;

    public function __construct(private readonly InventoryCountMovementService $service)
    {
    }

    public function index(Request $request): View|RedirectResponse
    {
        $tenantId = $this->tenantId($request);
        $scope = $this->resolveInventorySimpleScope($request);

        abort_if(! $scope, 403, 'Akun Anda belum ditetapkan ke outlet & departemen yang sah.');
        [$outletId, $departmentId] = $scope;

        $template = InventoryCountTemplate::query()
            ->where('tenant_id', $tenantId)
            ->where(fn ($q) => $q->where('department_id', $departmentId)->orWhere('is_generic', true))
            ->when($request->filled('template_id'), fn ($q) => $q->where('id', $request->integer('template_id')))
            ->with(['items.item.baseUnit'])
            ->orderByDesc('is_generic')
            ->orderBy('name')
            ->first();

        if (! $template) {
            return redirect()
                ->route('operations.inventory-count.index')
                ->with('warning', 'Belum ada template untuk departemen ini -- Rekap Mingguan butuh template yang sama dengan form hitung fisik harian.');
        }

        $weekStartParam = $request->date('week');
        $weekStart = $this->service->weekStart($weekStartParam ? Carbon::instance($weekStartParam) : now());

        $items = $template->items()->where('is_active', true)->orderBy('sort_order')->get();

        $rows = $items->map(function ($templateItem) use ($tenantId, $outletId, $departmentId, $weekStart) {
            $item = $templateItem->item;
            $result = $this->service->calculateWeekBalances($tenantId, $outletId, $departmentId, $item->id, $weekStart);

            return [
                'item' => $item,
                'subcategory' => $templateItem->subcategory_label ?: 'Lainnya',
                'unit' => $item->baseUnit?->abbreviation ?? $item->baseUnit?->code ?? '',
                'result' => $result,
            ];
        });

        return view('operations.inventory-count.movements', [
            'template' => $template,
            'outlet' => Outlet::query()->find($outletId),
            'department' => Department::query()->find($departmentId),
            'weekStart' => $weekStart,
            'rows' => $rows,
            'distinctCategories' => $rows->pluck('subcategory')->unique()->sort()->values(),
            'isSuperAdmin' => $this->isSuperAdmin($request),
        ]);
    }

    public function autosave(Request $request, Item $item): JsonResponse
    {
        $tenantId = $this->tenantId($request);
        $scope = $this->resolveInventorySimpleScope($request);

        abort_if(! $scope, 403, 'Akun Anda belum ditetapkan ke outlet & departemen yang sah.');
        abort_if((int) $item->tenant_id !== $tenantId, 404, 'Item tidak ditemukan.');
        [$outletId, $departmentId] = $scope;

        try {
            $validated = $request->validate([
                'date' => ['required', 'date'],
                'qty_in' => ['nullable', Decimal::validationRule(6)],
                'qty_out' => ['nullable', Decimal::validationRule(6)],
            ]);
        } catch (ValidationException $exception) {
            return response()->json(['message' => 'Validasi gagal.', 'errors' => $exception->errors()], 422);
        }

        $date = Carbon::parse($validated['date']);

        $this->service->recordMovement(
            $tenantId, $outletId, $departmentId, $item->id, $date,
            $validated['qty_in'] ?? null, $validated['qty_out'] ?? null,
            (int) $request->user()->id
        );

        $weekStart = $this->service->weekStart($date);
        $result = $this->service->calculateWeekBalances($tenantId, $outletId, $departmentId, $item->id, $weekStart);

        return response()->json(['week' => $result]);
    }

    public function saveOpeningBalance(Request $request, Item $item): JsonResponse
    {
        $tenantId = $this->tenantId($request);
        $scope = $this->resolveInventorySimpleScope($request);

        abort_if(! $scope, 403, 'Akun Anda belum ditetapkan ke outlet & departemen yang sah.');
        abort_if((int) $item->tenant_id !== $tenantId, 404, 'Item tidak ditemukan.');
        [$outletId, $departmentId] = $scope;

        try {
            $validated = $request->validate([
                'week_start' => ['required', 'date'],
                'qty' => ['required', Decimal::validationRule(6)],
            ]);
        } catch (ValidationException $exception) {
            return response()->json(['message' => 'Validasi gagal.', 'errors' => $exception->errors()], 422);
        }

        $weekStart = Carbon::parse($validated['week_start']);

        $this->service->recordOpeningBalance(
            $tenantId, $outletId, $departmentId, $item->id, $weekStart,
            $validated['qty'], (int) $request->user()->id
        );

        $result = $this->service->calculateWeekBalances($tenantId, $outletId, $departmentId, $item->id, $this->service->weekStart($weekStart));

        return response()->json(['week' => $result]);
    }

    private function tenantId(Request $request): int
    {
        $tenantId = $request->user()?->tenant_id;
        abort_unless($tenantId, 403, 'Tenant belum terpasang pada user.');

        return (int) $tenantId;
    }
}
