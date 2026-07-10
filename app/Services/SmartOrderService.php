<?php

namespace App\Services;

use App\Modules\Stock\Models\StockMutation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SmartOrderService
{
    /**
     * @return array<string, mixed>
     */
    public function getSuggestion(int $itemId, int $outletId, int $tenantId): array
    {
        $item = DB::table('items')
            ->where('tenant_id', $tenantId)
            ->where('id', $itemId)
            ->first([
                'id',
                'base_unit_id',
                'inventory_unit_id',
                'purchase_unit_id',
                'inventory_ratio',
                'purchase_ratio',
            ]);

        $outlet = DB::table('outlets')
            ->where('tenant_id', $tenantId)
            ->where('id', $outletId)
            ->first(['id', 'brand_id']);

        if (! $item || ! $outlet) {
            throw ValidationException::withMessages([
                'item_id' => 'Item atau outlet tidak ditemukan untuk tenant ini.',
            ]);
        }

        $config = DB::table('item_stock_configs')
            ->where('tenant_id', $tenantId)
            ->where('item_id', $itemId)
            ->where('outlet_id', $outletId)
            ->first();

        $usageDays = max(1, (int) ($config->avg_daily_usage_days ?? 7));
        $usageTotal = abs((float) DB::table('stock_mutations')
            ->where('tenant_id', $tenantId)
            ->where('outlet_id', $outletId)
            ->where('item_id', $itemId)
            ->whereIn('mutation_type', [
                StockMutation::TYPE_DAILY_OPNAME_ADJ,
                StockMutation::TYPE_SPOIL_WASTE,
            ])
            ->where('performed_at', '>=', now()->subDays($usageDays))
            ->where('qty_change', '<', 0)
            ->sum('qty_change'));

        $avgDailyUsage = $usageTotal / $usageDays;
        $currentQty = (float) DB::table('stock_balances')
            ->where('tenant_id', $tenantId)
            ->where('outlet_id', $outletId)
            ->where('item_id', $itemId)
            ->sum('qty_on_hand');

        $events = DB::table('calendar_events')
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->where(function ($query): void {
                $query->whereBetween('event_date', [
                    today()->toDateString(),
                    today()->addDays(30)->toDateString(),
                ])->orWhere(function ($ongoing): void {
                    $ongoing->whereDate('event_date', '<', today())
                        ->whereDate('event_end_date', '>=', today());
                });
            })
            ->where(function ($query) use ($outletId): void {
                $query->whereNull('outlet_id')
                    ->orWhere('outlet_id', $outletId);
            })
            ->where(function ($query) use ($outlet): void {
                $query->whereNull('brand_id');

                if ($outlet->brand_id) {
                    $query->orWhere('brand_id', $outlet->brand_id);
                }
            })
            ->orderBy('event_date')
            ->get([
                'id',
                'name',
                'event_date',
                'event_end_date',
                'event_type',
                'demand_multiplier',
            ]);

        $recommended = max(0.0, ($avgDailyUsage * 14) - $currentQty);
        foreach ($events as $event) {
            $recommended *= (float) $event->demand_multiplier;
        }

        $unitId = $config?->unit_id ?: $item->base_unit_id;
        $unitFactor = $this->unitFactor($item, $unitId, $tenantId);
        $minStockQty = (float) ($config->min_stock_qty ?? 0);
        $maxStockQty = (float) ($config->max_stock_qty ?? 0);
        $reorderPoint = (float) ($config->reorder_point ?? 0);
        $minStockBase = $minStockQty * $unitFactor;
        $maxStockBase = $maxStockQty * $unitFactor;
        $reorderPointBase = $reorderPoint * $unitFactor;

        if ($maxStockBase > 0) {
            $recommended = min($recommended, max(0.0, $maxStockBase - $currentQty));
        }

        $unit = $unitId
            ? DB::table('units')
                ->where('tenant_id', $tenantId)
                ->where('id', $unitId)
                ->value('abbreviation')
            : null;

        $daysRemaining = $avgDailyUsage > 0
            ? max(0.0, $currentQty / $avgDailyUsage)
            : null;

        return [
            'has_config' => $config !== null,
            'current_qty' => round($currentQty / $unitFactor, 4),
            'avg_daily_usage' => round($avgDailyUsage / $unitFactor, 4),
            'days_remaining' => $daysRemaining === null ? null : round($daysRemaining, 1),
            'min_stock_qty' => $minStockQty,
            'max_stock_qty' => $maxStockQty,
            'reorder_point' => $reorderPoint,
            'recommended_order' => round(max(0.0, $recommended) / $unitFactor, 4),
            'upcoming_events' => $events->map(function (object $event): array {
                $date = Carbon::parse($event->event_date);

                return [
                    'id' => $event->id,
                    'name' => $event->name,
                    'event_type' => $event->event_type,
                    'event_date' => $date->toDateString(),
                    'days_until' => max(0, today()->diffInDays($date, false)),
                    'demand_multiplier' => (float) $event->demand_multiplier,
                    'demand_change_pct' => round(((float) $event->demand_multiplier - 1) * 100),
                ];
            })->values()->all(),
            'is_below_reorder' => $config !== null && $currentQty <= $reorderPointBase,
            'is_critical' => $config !== null
                && ($currentQty <= $minStockBase || ($daysRemaining !== null && $daysRemaining <= 3)),
            'unit_abbreviation' => $unit ?: 'base',
        ];
    }

    private function unitFactor(object $item, ?int $unitId, int $tenantId): float
    {
        if (! $unitId || $unitId === (int) $item->base_unit_id) {
            return 1.0;
        }

        if ($unitId === (int) $item->inventory_unit_id) {
            return max(0.000001, (float) ($item->inventory_ratio ?: 1));
        }

        if ($unitId === (int) $item->purchase_unit_id) {
            return max(0.000001, (float) ($item->purchase_ratio ?: 1));
        }

        $factor = DB::table('unit_conversions')
            ->where('tenant_id', $tenantId)
            ->where('item_id', $item->id)
            ->where('from_unit_id', $unitId)
            ->where('to_unit_id', $item->base_unit_id)
            ->selectRaw('COALESCE(factor, multiply_rate) as conversion_factor')
            ->value('conversion_factor');

        return max(0.000001, (float) ($factor ?: 1));
    }
}
