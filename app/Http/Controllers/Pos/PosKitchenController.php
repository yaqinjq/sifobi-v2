<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Modules\Core\Models\Outlet;
use App\Modules\Pos\Models\PosOrder;
use App\Modules\Pos\Models\PosOrderItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PosKitchenController extends Controller
{
    public function index(Request $request): View
    {
        $tenantId = $this->tenantId($request);
        $user = $request->user();

        $outlets = Outlet::query()->where('tenant_id', $tenantId)->orderBy('name')->get(['id', 'name', 'code']);
        $outletId = (int) ($request->integer('outlet_id') ?: ($user->outlet_id ?: $outlets->first()?->id));

        $orders = PosOrder::query()
            ->where('tenant_id', $tenantId)
            ->where('outlet_id', $outletId)
            ->where('status', '!=', PosOrder::STATUS_VOID)
            ->whereHas('items', fn ($q) => $q->where('status', PosOrderItem::STATUS_PENDING))
            ->with(['table', 'items' => fn ($q) => $q->where('status', PosOrderItem::STATUS_PENDING)->orderBy('sort_order')])
            ->oldest('opened_at')
            ->get();

        if ($request->ajax()) {
            return view('pos.kitchen._list', compact('orders'));
        }

        return view('pos.kitchen.index', compact('outlets', 'outletId', 'orders'));
    }

    public function markReady(Request $request, PosOrderItem $item): RedirectResponse
    {
        abort_unless((int) $item->tenant_id === $this->tenantId($request), 403);

        $item->update([
            'status' => PosOrderItem::STATUS_READY,
            'prepared_at' => now(),
            'prepared_by' => $request->user()->id,
        ]);

        return redirect()->route('pos.kitchen.index', ['outlet_id' => $item->order->outlet_id])->with('success', 'Item ditandai siap.');
    }

    private function tenantId(Request $request): int
    {
        $tenantId = $request->user()?->tenant_id;

        abort_unless($tenantId, 403, 'Tenant belum terpasang pada user.');

        return (int) $tenantId;
    }
}
