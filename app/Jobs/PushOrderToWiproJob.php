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
 * Runs as a queue worker process (plain `php artisan queue:work`), never as a
 * PHP-FPM request. Wipro's Cloudflare edge consistently 404s any HTTP request
 * whose parent process is php-fpm — proven across Guzzle, PHP-cURL via
 * proc_open, and even the system curl binary invoked through proc_open, while
 * the identical curl binary run directly from a shell (any user) always
 * succeeds. Root cause undetermined; running the push from a queue worker
 * process sidesteps it entirely.
 */
class PushOrderToWiproJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(public int $purchaseOrderId) {}

    public function handle(WiproIntegrationService $wipro): void
    {
        $po = PurchaseOrder::find($this->purchaseOrderId);

        if (! $po) {
            return;
        }

        try {
            $result = $wipro->pushOrder($po);

            $po->forceFill([
                'external_reference'  => $result['wipro_order_number'] ?? $result['wipro_order_id'],
                'external_synced_at'  => now(),
                'external_sync_error' => null,
            ])->save();
        } catch (Throwable $exception) {
            $po->forceFill([
                'external_sync_error' => $exception->getMessage(),
            ])->save();
        }
    }
}
