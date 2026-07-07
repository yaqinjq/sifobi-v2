<?php

namespace App\Http\Controllers\Operations;

use App\Http\Controllers\Controller;
use App\Modules\Core\Models\Outlet;
use App\Modules\Operations\Models\OpnameItem;
use App\Modules\Operations\Models\OpnameSession;
use App\Services\OpnameService;
use App\Support\Decimal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

    public function show(OpnameSession $session): View
    {
        $session->load([
            'outlet',
            'createdBy',
            'submittedBy',
            'approvedBy',
            'items.item.inventoryUnit',
            'items.item.baseUnit',
            'items.department',
        ]);

        return view('operations.opname.show', [
            'session' => $session,
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
