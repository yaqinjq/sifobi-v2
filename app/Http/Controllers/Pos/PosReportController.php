<?php

namespace App\Http\Controllers\Pos;

use App\Exports\Reports\PosSalesExport;
use App\Http\Controllers\Controller;
use App\Modules\Core\Models\Outlet;
use App\Modules\Pos\Models\PosOrder;
use App\Modules\Pos\Models\PosPayment;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PosReportController extends Controller
{
    public function index(Request $request): View
    {
        $tenantId = $this->tenantId($request);
        $outlets = Outlet::query()->where('tenant_id', $tenantId)->orderBy('name')->get(['id', 'name', 'code']);

        [$outletId, $dateFrom, $dateTo] = $this->filters($request, $tenantId);

        $ordersQuery = $this->baseOrdersQuery($tenantId, $outletId, $dateFrom, $dateTo);

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
            'outlets', 'outletId', 'dateFrom', 'dateTo', 'totalOmzet', 'totalOrders', 'byOutlet', 'byMethod'
        ));
    }

    public function export(Request $request): BinaryFileResponse
    {
        $tenantId = $this->tenantId($request);

        [$outletId, $dateFrom, $dateTo] = $this->filters($request, $tenantId);

        return Excel::download(
            new PosSalesExport($tenantId, $outletId, $dateFrom, $dateTo),
            'LaporanPenjualanPOS.xlsx'
        );
    }

    /**
     * @return array{0: ?int, 1: Carbon, 2: Carbon}
     */
    private function filters(Request $request, int $tenantId): array
    {
        $validated = $request->validate([
            'outlet_id' => ['nullable', 'integer', Rule::exists('outlets', 'id')->where('tenant_id', $tenantId)],
            'date_from' => ['nullable', 'date'],
            'date_to'   => ['nullable', 'date'],
        ]);

        $outletId = $validated['outlet_id'] ?? null;
        $dateFrom = isset($validated['date_from']) ? Carbon::parse($validated['date_from']) : now();
        $dateTo = isset($validated['date_to']) ? Carbon::parse($validated['date_to']) : now();

        if ($dateFrom->gt($dateTo)) {
            [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
        }

        return [$outletId, $dateFrom, $dateTo];
    }

    private function baseOrdersQuery(int $tenantId, ?int $outletId, Carbon $dateFrom, Carbon $dateTo)
    {
        return PosOrder::query()
            ->where('tenant_id', $tenantId)
            ->where('status', PosOrder::STATUS_PAID)
            ->whereBetween('closed_at', [$dateFrom->copy()->startOfDay(), $dateTo->copy()->endOfDay()])
            ->when($outletId, fn ($q) => $q->where('outlet_id', $outletId));
    }

    private function tenantId(Request $request): int
    {
        $tenantId = $request->user()?->tenant_id;

        abort_unless($tenantId, 403, 'Tenant belum terpasang pada user.');

        return (int) $tenantId;
    }
}
