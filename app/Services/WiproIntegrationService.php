<?php

namespace App\Services;

use App\Modules\Core\Models\IntegrationProfile;
use App\Modules\Procurement\Models\PurchaseOrder;
use App\Modules\Receiving\Models\GoodsReceipt;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

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
        Log::error('[WIPRO] pushOrder request', [
            'po' => $po->po_number,
            'profile_id' => $profile->id,
            'tenant_id' => $po->tenant_id,
            'base_url' => $profile->base_url,
            'meta_order_path' => data_get($profile->meta, 'order_path'),
            'url' => $fullUrl,
        ]);
        $response = $this->client($profile)->post($fullUrl, $this->payload($po));
        Log::error('[WIPRO] pushOrder response', [
            'po' => $po->po_number,
            'status' => $response->status(),
            'headers' => $response->headers(),
            'body' => substr($response->body(), 0, 1000),
        ]);

        if (! $response->successful()) {
            throw new RuntimeException(
                'Wipro merespons HTTP '.$response->status().': '.($response->json('message') ?? $response->body())
            );
        }

        $body = $response->json();

        return [
            'wipro_order_id' => data_get($body, 'wipro_order_id'),
            'wipro_order_number' => data_get($body, 'wipro_order_number'),
            'duplicate' => (bool) data_get($body, 'duplicate', false),
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

    private function client(IntegrationProfile $profile): PendingRequest
    {
        // Plain `curl` to Wipro's endpoint always succeeds; PHP-cURL/Guzzle always gets
        // a bare-nginx 404 through Cloudflare (works fine hitting origin directly) — the
        // one consistent PHP-cURL vs curl-CLI difference is libcurl's automatic
        // "Expect: 100-continue" on POST bodies, which some proxies/edges mishandle.
        // Force HTTP/1.1 too, in case it's an HTTP/2 stream-multiplexing quirk on an
        // origin IP shared by many Cloudflare zones.
        $client = Http::timeout((int) data_get($profile->meta, 'timeout_seconds', 10))
            ->acceptJson()
            ->withUserAgent('Sifobi-Integration/1.0 (+https://new-fbi.mykopiogroup.com)')
            ->withOptions([
                'version' => 1.1,
                'expect' => false,
            ]);
        $authMode = $profile->auth_mode ?: $profile->auth_type;
        $token = $profile->auth_token ?: $profile->api_token;
        $username = $profile->auth_username ?: $profile->username;
        $password = $profile->auth_password ?: $profile->password;

        if ($authMode === 'BEARER' && $token) {
            return $client->withToken($token);
        }

        if ($authMode === 'BASIC' && $username) {
            return $client->withBasicAuth($username, (string) $password);
        }

        return $client;
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

        $path     = (string) (data_get($profile->meta, 'confirm_receipt_path') ?: '/api/fbi/confirm-receipt');
        $response = $this->client($profile)->post($this->url($profile, $path), $this->receiptPayload($gr));

        if (! $response->successful()) {
            throw new RuntimeException(
                'Wipro merespons HTTP '.$response->status().': '.($response->json('message') ?? $response->body())
            );
        }

        $body = $response->json();

        return [
            'success'   => (bool) data_get($body, 'success', true),
            'duplicate' => (bool) data_get($body, 'duplicate', false),
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
