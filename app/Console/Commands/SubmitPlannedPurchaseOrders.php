<?php

namespace App\Console\Commands;

use App\Modules\Procurement\Models\PurchaseOrder;
use App\Services\PurchaseOrderService;
use Illuminate\Console\Command;
use Throwable;

class SubmitPlannedPurchaseOrders extends Command
{
    protected $signature   = 'po:submit-planned';
    protected $description = 'Auto-submit DRAFT plan orders whose planned_submit_at date has arrived';

    public function handle(PurchaseOrderService $service): int
    {
        $candidates = PurchaseOrder::query()
            ->where('status', PurchaseOrder::STATUS_DRAFT)
            ->whereNotNull('planned_submit_at')
            ->where('planned_submit_at', '<=', now())
            ->whereHas('items')
            ->get();

        $this->info("Found {$candidates->count()} plan order(s) to submit.");

        foreach ($candidates as $po) {
            try {
                $service->submit($po, (int) $po->requested_by);
                $this->line("  ✓ Submitted PO #{$po->po_number}");
            } catch (Throwable $e) {
                $this->error("  ✗ Failed PO #{$po->po_number}: {$e->getMessage()}");
            }
        }

        return self::SUCCESS;
    }
}
