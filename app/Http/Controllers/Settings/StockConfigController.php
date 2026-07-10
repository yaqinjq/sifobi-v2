<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Modules\Core\Models\Outlet;
use App\Modules\Inventory\Models\Item;
use App\Modules\Inventory\Models\ItemStockConfig;
use App\Modules\Inventory\Models\Unit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class StockConfigController extends Controller
{
    public function index(Request $request): View
    {
        $tenantId = $this->tenantId($request);
        $outletId = $request->integer('outlet_id') ?: null;
        $search = trim($request->string('q')->toString());

        $configs = ItemStockConfig::query()
            ->with(['item.baseUnit', 'outlet', 'unit'])
            ->where('tenant_id', $tenantId)
            ->when($outletId, fn ($query) => $query->where('outlet_id', $outletId))
            ->when($search !== '', fn ($query) => $query->whereHas('item', fn ($itemQuery) => $itemQuery
                ->where('name', 'like', "%{$search}%")
                ->orWhere('canonical_sku', 'like', "%{$search}%")))
            ->orderBy('outlet_id')
            ->orderBy('item_id')
            ->paginate(30)
            ->withQueryString();

        return view('settings.stock-configs.index', [
            'configs' => $configs,
            'items' => Item::query()
                ->where('tenant_id', $tenantId)
                ->where('is_active', true)
                ->orderBy('name')
                ->limit(500)
                ->get(['id', 'name', 'canonical_sku', 'base_unit_id']),
            'outlets' => Outlet::query()
                ->where('tenant_id', $tenantId)
                ->where('status', 'ACTIVE')
                ->orderBy('name')
                ->get(),
            'units' => Unit::query()
                ->where('tenant_id', $tenantId)
                ->orderBy('name')
                ->get(),
            'outletId' => $outletId,
            'search' => $search,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $tenantId = $this->tenantId($request);
        $validated = $this->validated($request, $tenantId);

        DB::transaction(function () use ($tenantId, $validated): void {
            ItemStockConfig::query()->create([
                ...$validated,
                'tenant_id' => $tenantId,
            ]);
        });

        return back()->with('success', 'Konfigurasi stok berhasil ditambahkan.');
    }

    public function update(
        Request $request,
        ItemStockConfig $stockConfig
    ): RedirectResponse {
        $tenantId = $this->tenantId($request);
        $this->authorizeTenant($stockConfig, $tenantId);
        $validated = $this->validated($request, $tenantId, $stockConfig->id);

        DB::transaction(fn () => $stockConfig->update($validated));

        return back()->with('success', 'Konfigurasi stok berhasil diperbarui.');
    }

    public function destroy(
        Request $request,
        ItemStockConfig $stockConfig
    ): RedirectResponse {
        $tenantId = $this->tenantId($request);
        $this->authorizeTenant($stockConfig, $tenantId);

        DB::transaction(fn () => $stockConfig->delete());

        return back()->with('success', 'Konfigurasi stok berhasil dihapus.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, int $tenantId, ?int $ignoreId = null): array
    {
        return $request->validate([
            'item_id' => [
                'required',
                'integer',
                Rule::exists('items', 'id')->where('tenant_id', $tenantId),
                Rule::unique('item_stock_configs', 'item_id')
                    ->where(fn ($query) => $query
                        ->where('tenant_id', $tenantId)
                        ->where('outlet_id', $request->input('outlet_id')))
                    ->ignore($ignoreId),
            ],
            'outlet_id' => [
                'required',
                'integer',
                Rule::exists('outlets', 'id')->where('tenant_id', $tenantId),
            ],
            'min_stock_qty' => ['required', 'numeric', 'min:0'],
            'max_stock_qty' => ['required', 'numeric', 'gte:min_stock_qty'],
            'reorder_point' => ['required', 'numeric', 'min:0', 'lte:max_stock_qty'],
            'unit_id' => [
                'nullable',
                'integer',
                Rule::exists('units', 'id')->where('tenant_id', $tenantId),
            ],
            'avg_daily_usage_days' => ['required', 'integer', 'min:1', 'max:365'],
        ], [
            'item_id.unique' => 'Item ini sudah memiliki konfigurasi untuk outlet terpilih.',
            'max_stock_qty.gte' => 'Maksimum stok tidak boleh lebih kecil dari minimum stok.',
            'reorder_point.lte' => 'Reorder point tidak boleh melebihi maksimum stok.',
        ]);
    }

    private function tenantId(Request $request): int
    {
        $tenantId = $request->user()?->tenant_id;
        abort_unless($tenantId, 403, 'Tenant belum terpasang pada user.');

        return (int) $tenantId;
    }

    private function authorizeTenant(ItemStockConfig $config, int $tenantId): void
    {
        abort_unless((int) $config->tenant_id === $tenantId, 404);
    }
}
