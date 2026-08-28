<?php

namespace App\Http\Controllers;

use App\Modules\Operations\Models\OpenStock;
use App\Modules\Operations\Models\OpnameSession;
use App\Modules\Operations\Models\SpoilWaste;
use App\Modules\Procurement\Models\PurchaseOrder;
use App\Modules\Receiving\Models\GoodsReceipt;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        ini_set('memory_limit', '256M');

        $user = $request->user();
        $tenantId = $user->tenant_id;
        $outletId = $user->outlet_id;

        // Staff yang ditugaskan ke 1 outlet spesifik (outlet_id terisi)
        // cuma boleh lihat data outlet mereka sendiri di Dashboard — sama
        // persis pola yang sudah dipakai PurchaseOrderController. User
        // tanpa outlet_id (Admin/Manager Area/PIC lintas outlet) tetap
        // lihat data tenant-wide seperti sebelumnya.
        $tenantTable = fn (string $table, bool $hasOutletColumn = true) => $tenantId
            ? DB::table($table)->where('tenant_id', $tenantId)
                ->when($hasOutletColumn && $outletId, fn ($q) => $q->where('outlet_id', $outletId))
            : DB::table($table);

        $balanceTotals = $tenantTable('stock_balances')
            ->select(['tenant_id', 'outlet_id', 'item_id'])
            ->selectRaw('SUM(qty_on_hand) as qty_on_hand')
            ->groupBy('tenant_id', 'outlet_id', 'item_id');

        $unitFactorSql = 'CASE
            WHEN isc.unit_id IS NULL OR isc.unit_id = i.base_unit_id THEN 1
            WHEN isc.unit_id = i.inventory_unit_id THEN COALESCE(i.inventory_ratio, 1)
            WHEN isc.unit_id = i.purchase_unit_id THEN COALESCE(i.purchase_ratio, 1)
            ELSE COALESCE(uc.factor, uc.multiply_rate, 1)
        END';

        $lowStockItems = DB::query()
            ->fromSub($balanceTotals, 'sb')
            ->join('items as i', function ($join): void {
                $join->on('i.id', '=', 'sb.item_id')
                    ->on('i.tenant_id', '=', 'sb.tenant_id');
            })
            ->join('item_stock_configs as isc', function ($join): void {
                $join->on('isc.item_id', '=', 'sb.item_id')
                    ->on('isc.outlet_id', '=', 'sb.outlet_id')
                    ->on('isc.tenant_id', '=', 'sb.tenant_id');
            })
            ->leftJoin('unit_conversions as uc', function ($join): void {
                $join->on('uc.tenant_id', '=', 'sb.tenant_id')
                    ->on('uc.item_id', '=', 'sb.item_id')
                    ->on('uc.from_unit_id', '=', 'isc.unit_id')
                    ->on('uc.to_unit_id', '=', 'i.base_unit_id');
            })
            ->where('sb.tenant_id', $tenantId)
            ->when($outletId, fn ($q) => $q->where('sb.outlet_id', $outletId))
            ->where('sb.qty_on_hand', '>', 0)
            ->whereRaw("sb.qty_on_hand <= (isc.reorder_point * {$unitFactorSql})")
            ->select([
                'i.id',
                'i.name',
                'sb.qty_on_hand',
                'isc.min_stock_qty',
                'isc.reorder_point',
            ])
            ->orderBy('qty_on_hand')
            ->limit(5)
            ->get();

        return view('dashboard.index', [
            'user' => $user,
            'roles' => $user->getRoleNames()->values(),
            'lowStockItems' => $lowStockItems,
            'metrics' => [
                'total_outlets' => $outletId ? 1 : $tenantTable('outlets', false)->count(),
                'total_items' => $tenantTable('items', false)->where('is_active', true)
                    ->when($outletId, fn ($q) => $q->whereExists(fn ($sub) => $sub
                        ->selectRaw('1')->from('item_outlets')
                        ->whereColumn('item_outlets.item_id', 'items.id')
                        ->where('item_outlets.outlet_id', $outletId)))
                    ->count(),
                'total_stock_mutations' => $tenantTable('stock_mutations')->count(),
                'total_stock_balances' => $tenantTable('stock_balances')->count(),
                'open_stock_pending' => $tenantTable('open_stocks')->whereIn('status', ['DRAFT', 'PENDING'])->count(),
                'open_stock_draft' => $tenantTable('open_stocks')->where('status', OpenStock::STATUS_DRAFT)->count(),
                'open_stock_posted' => $tenantTable('open_stocks')->where('status', OpenStock::STATUS_POSTED)->count(),
                'item_aktif' => $tenantTable('items', false)->where('is_active', true)
                    ->when($outletId, fn ($q) => $q->whereExists(fn ($sub) => $sub
                        ->selectRaw('1')->from('item_outlets')
                        ->whereColumn('item_outlets.item_id', 'items.id')
                        ->where('item_outlets.outlet_id', $outletId)))
                    ->count(),
                'open_stock_today' => $tenantTable('open_stocks')->whereDate('created_at', today())->count(),
                'stok_menipis' => $tenantTable('stock_balances')->where('qty_on_hand', '<=', 0)->count(),
                'spoil_today' => $tenantTable('spoil_wastes')->whereDate('recorded_date', today())->count(),
                'penerimaan_pending' => $tenantTable('goods_receipts')->where('status', GoodsReceipt::STATUS_SUBMITTED)->count(),
                'opname_draft' => $tenantTable('opname_sessions')->where('status', OpnameSession::STATUS_DRAFT)->whereDate('opname_date', today())->count(),
                'opname_pending' => $tenantTable('opname_sessions')->where('status', OpnameSession::STATUS_SUBMITTED)->count(),
                'receiving_pending_review' => $tenantTable('goods_receipts')->where('status', GoodsReceipt::STATUS_SUBMITTED)->count(),
                'spoil_pending_approval' => $tenantTable('spoil_wastes')->where('status', SpoilWaste::STATUS_PENDING)->count(),
                'po_draft'               => $tenantTable('purchase_orders')->where('status', PurchaseOrder::STATUS_DRAFT)->count(),
                'po_submitted'           => $tenantTable('purchase_orders')->where('status', PurchaseOrder::STATUS_SUBMITTED)->count(),
                'po_sent_today'          => $tenantTable('purchase_orders')->where('status', PurchaseOrder::STATUS_SENT)->whereDate('sent_at', today())->count(),
            ],
            'latestMutations' => DB::table('stock_mutations as sm')
                ->join('items as i', 'i.id', '=', 'sm.item_id')
                ->when($tenantId, fn ($query) => $query->where('sm.tenant_id', $tenantId))
                ->when($outletId, fn ($query) => $query->where('sm.outlet_id', $outletId))
                ->select([
                    'sm.id',
                    'sm.item_id',
                    'sm.performed_at',
                    'sm.mutation_type',
                    'sm.qty_change',
                    'i.name as item_name',
                    'i.canonical_sku',
                ])
                ->orderByDesc('sm.performed_at')
                ->limit(10)
                ->get(),
        ]);
    }
}
