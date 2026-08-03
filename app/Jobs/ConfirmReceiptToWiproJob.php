<?php

namespace App\Jobs;

use App\Modules\Receiving\Models\GoodsReceipt;
use App\Services\WiproIntegrationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Mirrors PushOrderToWiproJob's queue-worker pattern for consistency — see
 * that job's docblock for why pushes to Wipro run from a queue worker rather
 * than synchronously in the web request.
 */
class ConfirmReceiptToWiproJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public int $backoff = 15;

    public function __construct(public int $goodsReceiptId) {}

    public function handle(WiproIntegrationService $wipro): void
    {
        $receipt = GoodsReceipt::find($this->goodsReceiptId);

        if (! $receipt) {
            return;
        }

        $wipro->confirmReceipt($receipt->fresh(['purchaseOrder', 'outlet', 'submittedBy', 'items.item', 'items.unit']));

        $receipt->forceFill([
            'receipt_synced_at' => now(),
            'receipt_sync_error' => null,
        ])->save();
    }

    public function failed(Throwable $exception): void
    {
        $receipt = GoodsReceipt::find($this->goodsReceiptId);

        $receipt?->forceFill([
            'receipt_sync_error' => $exception->getMessage(),
        ])->save();
    }
}
