<?php

namespace App\Http\Controllers;

use App\Modules\Core\Models\Outlet;
use App\Modules\Inventory\Models\Item;
use App\Modules\Operations\Models\OpenStock;
use App\Modules\Operations\Models\OpnameSession;
use App\Modules\Operations\Models\SpoilWaste;
use App\Modules\Receiving\Models\GoodsReceipt;
use App\Modules\Stock\Models\StockBalance;
use App\Modules\Stock\Models\StockMutation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $tenantId = $user->tenant_id;

        $tenantScope = fn ($query) => $tenantId
            ? $query->where('tenant_id', $tenantId)
            : $query;

        return view('dashboard.index', [
            'user' => $user,
            'roles' => $user->getRoleNames()->values(),
            'metrics' => [
                'total_outlets' => $tenantScope(Outlet::query())->count(),
                'total_items' => $tenantScope(Item::query())->where('is_active', true)->count(),
                'total_stock_mutations' => $tenantScope(StockMutation::query())->count(),
                'total_stock_balances' => $tenantScope(StockBalance::query())->count(),
                'open_stock_pending' => $tenantScope(OpenStock::query())->whereIn('status', ['DRAFT', 'PENDING'])->count(),
                'open_stock_draft' => $tenantScope(OpenStock::query())->where('status', OpenStock::STATUS_DRAFT)->count(),
                'open_stock_posted' => $tenantScope(OpenStock::query())->where('status', OpenStock::STATUS_POSTED)->count(),
                'item_aktif' => $tenantScope(Item::query())->where('is_active', true)->count(),
                'open_stock_today' => $tenantScope(OpenStock::query())->whereDate('created_at', today())->count(),
                'stok_menipis' => $tenantScope(StockBalance::query())->where('qty_on_hand', '<=', 0)->count(),
                'spoil_today' => $tenantScope(SpoilWaste::query())->whereDate('recorded_date', today())->count(),
                'penerimaan_pending' => $tenantScope(GoodsReceipt::query())->where('status', GoodsReceipt::STATUS_SUBMITTED)->count(),
                'opname_draft' => $tenantScope(OpnameSession::query())->where('status', OpnameSession::STATUS_DRAFT)->whereDate('opname_date', today())->count(),
                'opname_pending' => $tenantScope(OpnameSession::query())->where('status', OpnameSession::STATUS_SUBMITTED)->count(),
                'receiving_pending_review' => $tenantScope(GoodsReceipt::query())->where('status', GoodsReceipt::STATUS_SUBMITTED)->count(),
                'spoil_pending_approval' => $tenantScope(SpoilWaste::query())->where('approval_status', 'PENDING')->count(),
            ],
            'latestMutations' => DB::table('stock_mutations as sm')
                ->join('items as i', 'i.id', '=', 'sm.item_id')
                ->where('sm.tenant_id', $tenantId)
                ->select([
                    'sm.performed_at',
                    'sm.mutation_type',
                    'sm.qty_change',
                    'i.name as item_name',
                    'i.canonical_sku',
                ])
                ->orderByDesc('sm.performed_at')
                ->limit(5)
                ->get(),
        ]);
    }
}
