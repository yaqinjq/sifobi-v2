<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Modules\Core\Models\Outlet;
use App\Modules\Pos\Models\PosOrder;
use App\Modules\Pos\Models\PosPayment;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PosReportController extends Controller
{
    public function index(Request $request): View
    {
        $tenantId = $this->tenantId($request);

        $outlets = Outlet::query()->where('tenant_id', $tenantId)->orderBy('name')->get(['id', 'name', 'code']);
        $outletId = $request->integer('outlet_id') ?: null;
        $date = $request->date('date') ?? now();

        $ordersQuery = PosOrder::query()
            ->where('tenant_id', $tenantId)
            ->where('status', PosOrder::STATUS_PAID)
            ->whereDate('closed_at', $date->toDateString())
            ->when($outletId, fn ($q) => $q->where('outlet_id', $outletId));

        $totalOmzet = (clone $ordersQuery)->sum('total_amount');
        $totalOrders = (clone $ordersQuery)->count();

        $byOutlet = (clone $ordersQuery)
            ->with('outlet:id,name')
            ->get()
            ->groupBy('outlet_id')
            ->map(fn ($orders) => [
                'outlet' => $orders->first()->outlet,
                'total'  => $orders->sum('total_amount'),
                'count'  => $orders->count(),
            ]);

        $orderIds = (clone $ordersQuery)->pluck('id');

        $byMethod = PosPayment::query()
            ->whereIn('pos_order_id', $orderIds)
            ->selectRaw('method, sum(amount) as total, count(*) as total_tx')
            ->groupBy('method')
            ->get();

        return view('pos.reports.index', compact(
            'outlets', 'outletId', 'date', 'totalOmzet', 'totalOrders', 'byOutlet', 'byMethod'
        ));
    }

    private function tenantId(Request $request): int
    {
        $tenantId = $request->user()?->tenant_id;

        abort_unless($tenantId, 403, 'Tenant belum terpasang pada user.');

        return (int) $tenantId;
    }
}
