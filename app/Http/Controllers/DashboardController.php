<?php

namespace App\Http\Controllers;

use App\Modules\Operations\Models\OpenStock;
use App\Modules\Operations\Models\OpnameSession;
use App\Modules\Operations\Models\SpoilWaste;
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

        $tenantTable = fn (string $table) => $tenantId
            ? DB::table($table)->where('tenant_id', $tenantId)
            : DB::table($table);

        return view('dashboard.index', [
            'user' => $user,
            'roles' => $user->getRoleNames()->values(),
            'metrics' => [
                'total_outlets' => $tenantTable('outlets')->count(),
                'total_items' => $tenantTable('items')->where('is_active', true)->count(),
                'total_stock_mutations' => $tenantTable('stock_mutations')->count(),
                'total_stock_balances' => $tenantTable('stock_balances')->count(),
                'open_stock_pending' => $tenantTable('open_stocks')->whereIn('status', ['DRAFT', 'PENDING'])->count(),
                'open_stock_draft' => $tenantTable('open_stocks')->where('status', OpenStock::STATUS_DRAFT)->count(),
                'open_stock_posted' => $tenantTable('open_stocks')->where('status', OpenStock::STATUS_POSTED)->count(),
                'item_aktif' => $tenantTable('items')->where('is_active', true)->count(),
                'open_stock_today' => $tenantTable('open_stocks')->whereDate('created_at', today())->count(),
                'stok_menipis' => $tenantTable('stock_balances')->where('qty_on_hand', '<=', 0)->count(),
                'spoil_today' => $tenantTable('spoil_wastes')->whereDate('recorded_date', today())->count(),
                'penerimaan_pending' => $tenantTable('goods_receipts')->where('status', GoodsReceipt::STATUS_SUBMITTED)->count(),
                'opname_draft' => $tenantTable('opname_sessions')->where('status', OpnameSession::STATUS_DRAFT)->whereDate('opname_date', today())->count(),
                'opname_pending' => $tenantTable('opname_sessions')->where('status', OpnameSession::STATUS_SUBMITTED)->count(),
                'receiving_pending_review' => $tenantTable('goods_receipts')->where('status', GoodsReceipt::STATUS_SUBMITTED)->count(),
                'spoil_pending_approval' => $tenantTable('spoil_wastes')->where('status', SpoilWaste::STATUS_PENDING)->count(),
            ],
            'latestMutations' => DB::table('stock_mutations as sm')
                ->join('items as i', 'i.id', '=', 'sm.item_id')
                ->when($tenantId, fn ($query) => $query->where('sm.tenant_id', $tenantId))
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
