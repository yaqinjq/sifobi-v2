<?php

namespace App\Services;

use App\Modules\Core\Models\IntegrationProfile;
use App\Modules\Procurement\Models\PurchaseOrder;
use App\Modules\Receiving\Models\GoodsReceipt;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Symfony\Component\Process\Process;

class WiproIntegrationService
{
    public function profile(int $tenantId): ?IntegrationProfile
    {
        return IntegrationProfile::query()
            ->where('tenant_id', $tenantId)
            ->where('code', 'WIPRO')
            ->where('is_active', true)
            ->first();
    }

    /**
     * Push an approved Central Kitchen PO to Wipro.
     * Throws on failure — caller decides whether to block the SENT transition.
     *
     * @return array{wipro_order_id: mixed, wipro_order_number: mixed, duplicate: bool}
     */
    public function pushOrder(PurchaseOrder $po): array
    {
        $profile = $this->profile((int) $po->tenant_id);

        if (! $profile) {
            throw new RuntimeException('Integrasi WIPRO belum dikonfigurasi atau tidak aktif untuk tenant ini.');
        }

        $po->loadMissing(['outlet', 'requestedBy', 'items.item', 'items.unit']);

        $path = (string) (data_get($profile->meta, 'order_path') ?: '/api/fbi/orders');
        $fullUrl = $this->url($profile, $path);

        $result = $this->curlPost($profile, $fullUrl, $this->payload($po));
        $json = json_decode($result['body'], true);

        if ($result['status'] < 200 || $result['status'] >= 300) {
            Log::error('[WIPRO] pushOrder failed', ['po' => $po->po_number, 'status' => $result['status'], 'body' => $result['body']]);

            throw new RuntimeException(
                'Wipro merespons HTTP '.$result['status'].': '.(data_get($json, 'message') ?? $result['body'])
            );
        }

        return [
            'wipro_order_id' => data_get($json, 'wipro_order_id'),
            'wipro_order_number' => data_get($json, 'wipro_order_number'),
            'duplicate' => (bool) data_get($json, 'duplicate', false),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(PurchaseOrder $po): array
    {
        return [
            'order_no' => $po->po_number,
            'external_po_id' => $po->id,
            'outlet_code' => $po->outlet?->code,
            'outlet_name' => $po->outlet?->name,
            'requested_date' => optional($po->needed_at)->toDateString() ?: now()->toDateString(),
            'needed_at' => optional($po->needed_at)->toDateString(),
            'requested_by_name' => $po->requestedBy?->name,
            'notes' => $po->notes,
            'items' => $po->items->map(fn ($item) => [
                'external_item_id' => $item->item_id,
                'sku' => $item->item?->canonical_sku,
                'qty' => (float) $item->qty_ordered,
                'uom' => $item->unit?->code,
                'notes' => $item->notes,
            ])->values()->all(),
        ];
    }

    /**
     * Shells out to the system `curl` binary instead of Guzzle. Wipro's Cloudflare
     * edge consistently 404s PHP-cURL/Guzzle requests (proven not to be UA, Expect:
     * 100-continue, or HTTP version — all ruled out) while plain `curl` from the same
     * server always succeeds; the two link against different libcurl/OpenSSL builds
     * on this box, so this is almost certainly a TLS-handshake fingerprint difference
     * at Cloudflare's edge. Shelling out to the binary that's proven to work is the
     * pragmatic fix.
     *
     * @return array{status: int, body: string}
     */
    private function curlPost(IntegrationProfile $profile, string $url, array $payload): array
    {
        $authMode = $profile->auth_mode ?: $profile->auth_type;
        $token = $profile->auth_token ?: $profile->api_token;
        $username = $profile->auth_username ?: $profile->username;
        $password = $profile->auth_password ?: $profile->password;
        $timeout = (int) data_get($profile->meta, 'timeout_seconds', 10);

        $args = [
            'curl', '-s', '-X', 'POST', $url,
            '-H', 'Content-Type: application/json',
            '-H', 'Accept: application/json',
            '--max-time', (string) $timeout,
            '-d', json_encode($payload),
            '-w', "\n%{http_code}",
        ];

        if ($authMode === 'BEARER' && $token) {
            $args[] = '-H';
            $args[] = "Authorization: Bearer {$token}";
        } elseif ($authMode === 'BASIC' && $username) {
            $args[] = '-u';
            $args[] = "{$username}:{$password}";
        }

        $process = new Process($args);
        $process->setTimeout($timeout + 5);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException('Gagal menjalankan curl ke Wipro: '.$process->getErrorOutput());
        }

        $output = $process->getOutput();
        $splitAt = strrpos($output, "\n");

        return [
            'status' => (int) trim(substr($output, $splitAt + 1)),
            'body' => $splitAt !== false ? substr($output, 0, $splitAt) : '',
        ];
    }

    /**
     * Notify Wipro that goods from a Central Kitchen order have been received at the outlet.
     * Wire this in GoodsReceiptService::postToLedger() once Wipro's confirm-receipt endpoint is live.
     *
     * @return array{success: bool, duplicate: bool}
     */
    public function confirmReceipt(GoodsReceipt $gr): array
    {
        $profile = $this->profile((int) $gr->tenant_id);

        if (! $profile) {
            throw new RuntimeException('Integrasi WIPRO belum dikonfigurasi atau tidak aktif untuk tenant ini.');
        }

        $gr->loadMissing(['purchaseOrder', 'outlet', 'submittedBy', 'items.item', 'items.unit']);

        $path = (string) (data_get($profile->meta, 'confirm_receipt_path') ?: '/api/fbi/confirm-receipt');
        $result = $this->curlPost($profile, $this->url($profile, $path), $this->receiptPayload($gr));
        $json = json_decode($result['body'], true);

        if ($result['status'] < 200 || $result['status'] >= 300) {
            throw new RuntimeException(
                'Wipro merespons HTTP '.$result['status'].': '.(data_get($json, 'message') ?? $result['body'])
            );
        }

        return [
            'success'   => (bool) data_get($json, 'success', true),
            'duplicate' => (bool) data_get($json, 'duplicate', false),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function receiptPayload(GoodsReceipt $gr): array
    {
        $po = $gr->purchaseOrder;

        return [
            'wipro_order_number' => $po?->external_reference,
            'external_po_id'     => $po?->id,
            'sifobi_gr_number'   => $gr->receipt_number,
            'outlet_code'        => $gr->outlet?->code,
            'outlet_name'        => $gr->outlet?->name,
            'received_date'      => optional($gr->receipt_date)->toDateString(),
            'received_at'        => optional($gr->approved_at ?? $gr->received_at)->toIso8601String(),
            'received_by'        => $gr->submittedBy?->name,
            'notes'              => $gr->notes,
            'items'              => $gr->items->map(fn ($item) => [
                'sku'           => $item->item?->canonical_sku,
                'qty_ordered'   => (float) $item->qty_ordered,
                'qty_received'  => (float) $item->qty_received,
                'qty_short'     => (float) $item->qty_short,
                'qty_over'      => (float) $item->qty_over,
                'uom'           => $item->unit?->code,
                'notes'         => $item->notes,
            ])->values()->all(),
        ];
    }

    private function url(IntegrationProfile $profile, string $path): string
    {
        return rtrim($profile->base_url, '/').'/'.ltrim($path ?: '/', '/');
    }
}
