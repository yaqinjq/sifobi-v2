<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Modules\Core\Models\Outlet;
use App\Modules\Pos\Models\PosOrder;
use App\Modules\Pos\Models\PosOrderItem;
use App\Modules\Pos\Models\PosPayment;
use App\Modules\Pos\Models\PosTable;
use App\Modules\Production\Models\Menu;
use App\Services\PosOrderService;
use App\Services\PosShiftService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PosOrderController extends Controller
{
    public function __construct(
        private readonly PosOrderService $service,
        private readonly PosShiftService $shiftService,
    ) {}

    public function index(Request $request): View
    {
        $tenantId = $this->tenantId($request);
        $user = $request->user();

        $outlets = Outlet::query()->where('tenant_id', $tenantId)->orderBy('name')->get(['id', 'name', 'code']);
        $outletId = (int) ($request->integer('outlet_id') ?: ($user->outlet_id ?: $outlets->first()?->id));

        $orders = PosOrder::query()
            ->where('tenant_id', $tenantId)
            ->where('outlet_id', $outletId)
            ->where('status', PosOrder::STATUS_OPEN)
            ->with(['table', 'items'])
            ->latest('opened_at')
            ->get();

        $hasOpenShift = (bool) $this->shiftService->findOpenShift($tenantId, $outletId);

        return view('pos.orders.index', compact('orders', 'outlets', 'outletId', 'hasOpenShift'));
    }

    public function create(Request $request): View
    {
        $tenantId = $this->tenantId($request);
        $user = $request->user();

        $outlets = Outlet::query()->where('tenant_id', $tenantId)->orderBy('name')->get(['id', 'name', 'code']);
        $outletId = (int) ($request->integer('outlet_id') ?: ($user->outlet_id ?: $outlets->first()?->id));

        $tables = PosTable::query()
            ->where('tenant_id', $tenantId)
            ->where('outlet_id', $outletId)
            ->where('status', PosTable::STATUS_AVAILABLE)
            ->orderBy('code')
            ->get();

        $preselectedTableId = $request->integer('table_id') ?: null;

        return view('pos.orders.create', compact('outlets', 'outletId', 'tables', 'preselectedTableId'));
    }

    public function store(Request $request): RedirectResponse
    {
        $tenantId = $this->tenantId($request);

        $validated = $request->validate([
            'outlet_id'    => ['required', 'integer', Rule::exists('outlets', 'id')->where('tenant_id', $tenantId)],
            'order_type'   => ['required', Rule::in([PosOrder::TYPE_DINE_IN, PosOrder::TYPE_TAKEAWAY])],
            'pos_table_id' => ['nullable', 'integer', Rule::exists('pos_tables', 'id')->where('tenant_id', $tenantId)],
            'notes'        => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $order = $this->service->openOrder($validated, $tenantId, (int) $request->user()->id);
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors())->withInput();
        }

        return redirect()->route('pos.orders.show', $order)->with('success', 'Order berhasil dibuka.');
    }

    public function show(Request $request, PosOrder $order): View
    {
        abort_unless((int) $order->tenant_id === $this->tenantId($request), 403);

        $order->load(['items.menu', 'table', 'payments']);

        $menus = Menu::query()
            ->where('tenant_id', $order->tenant_id)
            ->where('is_active', true)
            ->whereNotNull('selling_price')
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'selling_price']);

        $remainingDue = bcsub((string) $order->total_amount, $order->amountPaid(), 4);
        $hasOpenShift = (bool) $this->shiftService->findOpenShift((int) $order->tenant_id, (int) $order->outlet_id);

        $otherOpenOrders = PosOrder::query()
            ->where('tenant_id', $order->tenant_id)
            ->where('outlet_id', $order->outlet_id)
            ->where('status', PosOrder::STATUS_OPEN)
            ->where('id', '!=', $order->id)
            ->get(['id', 'order_number']);

        return view('pos.orders.show', compact('order', 'menus', 'remainingDue', 'hasOpenShift', 'otherOpenOrders'));
    }

    public function addItem(Request $request, PosOrder $order): RedirectResponse
    {
        abort_unless((int) $order->tenant_id === $this->tenantId($request), 403);

        $validated = $request->validate([
            'menu_id' => ['required', 'integer', Rule::exists('menus', 'id')->where('tenant_id', $order->tenant_id)],
            'qty'     => ['required', 'numeric', 'min:0.01'],
            'notes'   => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $this->service->addItem($order, $validated);
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors());
        }

        return redirect()->route('pos.orders.show', $order)->with('success', 'Item ditambahkan.');
    }

    public function removeItem(Request $request, PosOrder $order, PosOrderItem $item): RedirectResponse
    {
        abort_unless((int) $order->tenant_id === $this->tenantId($request), 403);
        abort_unless((int) $item->pos_order_id === (int) $order->id, 404);

        try {
            $this->service->removeItem($order, $item);
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors());
        }

        return redirect()->route('pos.orders.show', $order)->with('success', 'Item dihapus.');
    }

    public function splitItem(Request $request, PosOrder $order, PosOrderItem $item): RedirectResponse
    {
        abort_unless((int) $order->tenant_id === $this->tenantId($request), 403);
        abort_unless((int) $item->pos_order_id === (int) $order->id, 404);

        $validated = $request->validate([
            'qty'             => ['required', 'numeric', 'min:0.01'],
            'target_order_id' => ['required', 'integer', Rule::exists('pos_orders', 'id')->where('tenant_id', $order->tenant_id)],
        ]);

        $targetOrder = PosOrder::query()->findOrFail($validated['target_order_id']);

        try {
            $result = $this->service->splitItem($item, $targetOrder, (string) $validated['qty']);
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors());
        }

        return redirect()
            ->route('pos.orders.show', $order)
            ->with('success', "Item berhasil dipindah ke order #{$result['target']->order_number}.");
    }

    public function mergeFrom(Request $request, PosOrder $order): RedirectResponse
    {
        abort_unless((int) $order->tenant_id === $this->tenantId($request), 403);

        $validated = $request->validate([
            'source_order_id' => [
                'required',
                'integer',
                Rule::exists('pos_orders', 'id')->where('tenant_id', $order->tenant_id),
            ],
        ]);

        $sourceOrder = PosOrder::query()->findOrFail($validated['source_order_id']);

        try {
            $this->service->mergeOrders($order, $sourceOrder);
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors());
        }

        return redirect()
            ->route('pos.orders.show', $order)
            ->with('success', "Order #{$sourceOrder->order_number} berhasil digabung ke order ini.");
    }

    public function checkout(Request $request, PosOrder $order): RedirectResponse
    {
        abort_unless((int) $order->tenant_id === $this->tenantId($request), 403);

        $validated = $request->validate([
            'discount_amount'       => ['nullable', 'numeric', 'min:0'],
            'tax_amount'            => ['nullable', 'numeric', 'min:0'],
            'service_charge_amount' => ['nullable', 'numeric', 'min:0'],
        ]);

        try {
            $this->service->checkout($order, $validated);
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors());
        }

        return redirect()->route('pos.orders.show', $order)->with('success', 'Total order dihitung, siap dibayar.');
    }

    public function pay(Request $request, PosOrder $order): RedirectResponse
    {
        abort_unless((int) $order->tenant_id === $this->tenantId($request), 403);

        $validated = $request->validate([
            'method'       => ['required', Rule::in(array_keys(PosPayment::METHOD_LABELS))],
            'amount'       => ['required', 'numeric', 'min:0.01'],
            'reference_no' => ['nullable', 'string', 'max:100'],
        ]);

        try {
            $order = $this->service->recordPayment($order, $validated, (int) $request->user()->id);
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors());
        }

        if ($order->status === PosOrder::STATUS_PAID) {
            return redirect()->route('pos.orders.receipt', $order)->with('success', 'Pembayaran berhasil, order lunas.');
        }

        return redirect()->route('pos.orders.show', $order)->with('success', 'Pembayaran sebagian dicatat.');
    }

    public function receipt(Request $request, PosOrder $order): View
    {
        abort_unless((int) $order->tenant_id === $this->tenantId($request), 403);

        $order->load(['items.menu', 'table', 'payments', 'outlet']);

        return view('pos.orders.receipt', compact('order'));
    }

    public function void(Request $request, PosOrder $order): RedirectResponse
    {
        abort_unless((int) $order->tenant_id === $this->tenantId($request), 403);

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $this->service->void($order, $validated['reason'] ?? '');
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors());
        }

        return redirect()->route('pos.orders.index', ['outlet_id' => $order->outlet_id])->with('success', 'Order dibatalkan.');
    }

    private function tenantId(Request $request): int
    {
        $tenantId = $request->user()?->tenant_id;

        abort_unless($tenantId, 403, 'Tenant belum terpasang pada user.');

        return (int) $tenantId;
    }
}
