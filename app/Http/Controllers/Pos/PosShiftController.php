<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Modules\Core\Models\Outlet;
use App\Modules\Pos\Models\PosShift;
use App\Services\PosShiftService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PosShiftController extends Controller
{
    public function __construct(private readonly PosShiftService $service) {}

    public function index(Request $request): View
    {
        $tenantId = $this->tenantId($request);
        $user = $request->user();

        $outlets = Outlet::query()->where('tenant_id', $tenantId)->orderBy('name')->get(['id', 'name', 'code']);
        $outletId = (int) ($request->integer('outlet_id') ?: ($user->outlet_id ?: $outlets->first()?->id));

        $currentShift = $this->service->findOpenShift($tenantId, $outletId);

        $history = PosShift::query()
            ->where('tenant_id', $tenantId)
            ->where('outlet_id', $outletId)
            ->where('status', '!=', PosShift::STATUS_OPEN)
            ->with('openedBy')
            ->latest('opened_at')
            ->paginate(10)
            ->withQueryString();

        return view('pos.shifts.index', compact('outlets', 'outletId', 'currentShift', 'history'));
    }

    public function store(Request $request): RedirectResponse
    {
        $tenantId = $this->tenantId($request);

        $validated = $request->validate([
            'outlet_id' => ['required', 'integer', 'exists:outlets,id'],
            'opening_cash' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $shift = $this->service->openShift($validated, $tenantId, (int) $validated['outlet_id'], (int) $request->user()->id);
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors())->withInput();
        }

        return redirect()->route('pos.shifts.show', $shift)->with('success', 'Shift kasir dibuka.');
    }

    public function show(Request $request, PosShift $shift): View
    {
        abort_unless((int) $shift->tenant_id === $this->tenantId($request), 403);

        $shift->load(['payments.order', 'openedBy', 'closedBy', 'reconciledBy']);

        return view('pos.shifts.show', compact('shift'));
    }

    public function close(Request $request, PosShift $shift): RedirectResponse
    {
        abort_unless((int) $shift->tenant_id === $this->tenantId($request), 403);

        $validated = $request->validate([
            'closing_cash_actual' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $this->service->closeShift($shift, $validated, (int) $request->user()->id);
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors());
        }

        return redirect()->route('pos.shifts.show', $shift)->with('success', 'Shift kasir ditutup.');
    }

    public function reconcile(Request $request, PosShift $shift): RedirectResponse
    {
        abort_unless((int) $shift->tenant_id === $this->tenantId($request), 403);

        $validated = $request->validate([
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $this->service->reconcile($shift, $validated, (int) $request->user()->id);
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors());
        }

        return redirect()->route('pos.shifts.show', $shift)->with('success', 'Shift kasir berhasil direkonsiliasi.');
    }

    private function tenantId(Request $request): int
    {
        $tenantId = $request->user()?->tenant_id;

        abort_unless($tenantId, 403, 'Tenant belum terpasang pada user.');

        return (int) $tenantId;
    }
}
