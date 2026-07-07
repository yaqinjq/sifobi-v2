<?php

namespace App\Http\Controllers\Stock;

use App\Http\Controllers\Controller;
use App\Modules\Core\Models\Outlet;
use App\Modules\Inventory\Models\Item;
use App\Modules\Inventory\Models\ItemCategory;
use App\Modules\Stock\Models\StockBalance;
use App\Modules\Stock\Models\StockMutation;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class StockBalanceController extends Controller
{
    public function index(Request $request): View
    {
        $tenantId = (int) $request->user()->tenant_id;
        abort_unless($tenantId, 403);

        $outlets = Outlet::query()
            ->where('tenant_id', $tenantId)
            ->where('status', 'ACTIVE')
            ->orderBy('name')
            ->get();

        $outletId = (int) ($request->integer('outlet_id') ?: ($request->user()->outlet_id ?: $outlets->first()?->id));
        $categoryId = $request->integer('category_id') ?: null;
        $search = trim((string) $request->get('q', ''));
        $showEmpty = $request->boolean('show_empty', false);
        $stockTarget = $request->get('stock_target');

        $query = StockBalance::query()
            ->with(['item.category', 'item.inventoryUnit', 'item.baseUnit', 'outlet'])
            ->where('tenant_id', $tenantId)
            ->when($outletId, fn ($builder) => $builder->where('outlet_id', $outletId))
            ->when($stockTarget, fn ($builder) => $builder->where('stock_target', $stockTarget));

        if (! $showEmpty) {
            $query->where('qty_on_hand', '>', 0);
        }

        if ($categoryId) {
            $query->whereHas('item', fn ($builder) => $builder->where('item_category_id', $categoryId));
        }

        if ($search !== '') {
            $like = "%{$search}%";
            $query->whereHas('item', function ($builder) use ($like): void {
                $builder->where('name', 'like', $like)
                    ->orWhere('canonical_sku', 'like', $like);
            });
        }

        $balances = $query
            ->orderBy(Item::select('name')->whereColumn('items.id', 'stock_balances.item_id'))
            ->paginate(30)
            ->withQueryString();

        $summary = DB::table('stock_balances')
            ->where('tenant_id', $tenantId)
            ->when($outletId, fn ($builder) => $builder->where('outlet_id', $outletId))
            ->when($stockTarget, fn ($builder) => $builder->where('stock_target', $stockTarget))
            ->selectRaw('
                COUNT(*) as total_items,
                SUM(CASE WHEN qty_on_hand <= 0 THEN 1 ELSE 0 END) as empty_items,
                COALESCE(SUM(total_value), 0) as total_inventory_value
            ')
            ->first();

        $categories = ItemCategory::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('stock.balance.index', [
            'balances' => $balances,
            'summary' => $summary,
            'outlets' => $outlets,
            'categories' => $categories,
            'selectedOutlet' => $outlets->firstWhere('id', $outletId),
            'outletId' => $outletId,
            'categoryId' => $categoryId,
            'search' => $search,
            'showEmpty' => $showEmpty,
            'stockTarget' => $stockTarget,
            'stockTargets' => $this->stockTargets(),
        ]);
    }

    public function show(Request $request, Item $item): View
    {
        $tenantId = (int) $request->user()->tenant_id;
        abort_unless($tenantId && (int) $item->tenant_id === $tenantId, 403);

        $outletId = (int) ($request->integer('outlet_id') ?: $request->user()->outlet_id);
        $stockTarget = $request->get('stock_target');

        $balances = StockBalance::query()
            ->with(['item.inventoryUnit', 'item.baseUnit', 'outlet'])
            ->where('tenant_id', $tenantId)
            ->where('item_id', $item->id)
            ->when($outletId, fn ($builder) => $builder->where('outlet_id', $outletId))
            ->when($stockTarget, fn ($builder) => $builder->where('stock_target', $stockTarget))
            ->orderBy('stock_target')
            ->get();

        $mutations = DB::table('stock_mutations as sm')
            ->join('outlets as o', 'o.id', '=', 'sm.outlet_id')
            ->leftJoin('users as u', 'u.id', '=', 'sm.performed_by')
            ->where('sm.tenant_id', $tenantId)
            ->where('sm.item_id', $item->id)
            ->when($outletId, fn ($builder) => $builder->where('sm.outlet_id', $outletId))
            ->when($stockTarget, fn ($builder) => $builder->where('sm.stock_target', $stockTarget))
            ->where('sm.performed_at', '>=', Carbon::now()->subDays(30))
            ->select([
                'sm.id',
                'sm.performed_at',
                'sm.mutation_type',
                'sm.stock_target',
                'sm.qty_change',
                'sm.balance_after',
                'sm.reference_type',
                'sm.reference_id',
                'sm.notes',
                'o.name as outlet_name',
                'u.name as user_name',
            ])
            ->orderByDesc('sm.performed_at')
            ->limit(100)
            ->get();

        return view('stock.balance.show', [
            'item' => $item->load(['category', 'inventoryUnit', 'baseUnit']),
            'balances' => $balances,
            'mutations' => $mutations,
            'stockTargets' => $this->stockTargets(),
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function stockTargets(): array
    {
        return [
            StockMutation::TARGET_OUTLET_DAILY => 'Stok Harian Outlet',
            StockMutation::TARGET_OUTLET_WAREHOUSE => 'Gudang Outlet',
        ];
    }
}
