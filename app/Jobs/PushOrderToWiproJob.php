<?php

namespace App\Jobs;

use App\Modules\Procurement\Models\PurchaseOrder;
use App\Services\WiproIntegrationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Wipro's Cloudflare zone intermittently 404s a fraction of requests — proven
 * to vary by which anycast edge node the connection happens to land on, not
 * by anything on this side (UA, TLS, TTY, process ancestry, env, curl binary
 * all ruled out; two curl calls seconds apart landed on different Cloudflare
 * edge IPs, one clean, one broken). So: let handle() throw on failure so
 * Laravel's own retry/backoff actually re-dials (each attempt is a fresh
 * connection, likely a different edge), and only record a failure on the PO
 * once every retry is exhausted via failed().
 */
class PushOrderToWiproJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public int $backoff = 15;

    public function __construct(public int $purchaseOrderId) {}

    public function handle(WiproIntegrationService $wipro): void
    {
        $po = PurchaseOrder::find($this->purchaseOrderId);

        if (! $po) {
            return;
        }

        $result = $wipro->pushOrder($po);

        $po->forceFill([
            'external_reference'  => $result['wipro_order_number'] ?? $result['wipro_order_id'],
            'external_synced_at'  => now(),
            'external_sync_error' => null,
        ])->save();
    }

    public function failed(Throwable $exception): void
    {
        $po = PurchaseOrder::find($this->purchaseOrderId);

        $po?->forceFill([
            'external_sync_error' => $exception->getMessage(),
        ])->save();
    }
}
