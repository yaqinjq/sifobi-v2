<?php

namespace App\Services;

use App\Modules\Core\Models\IntegrationProfile;
use App\Modules\Procurement\Models\PurchaseOrder;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class OciaIntegrationService
{
    public function profile(int $tenantId): ?IntegrationProfile
    {
        return IntegrationProfile::query()
            ->where('tenant_id', $tenantId)
            ->where('code', 'OCIA')
            ->where('is_active', true)
            ->first();
    }

    /**
     * Push an approved OCIA Roastery PO to the OCIA system.
     * Throws on failure — caller decides whether to block the SENT transition.
     *
     * @return array{ocia_order_id: mixed, ocia_order_number: mixed, duplicate: bool}
     */
    public function pushOrder(PurchaseOrder $po): array
    {
        $profile = $this->profile((int) $po->tenant_id);

        if (! $profile) {
            throw new RuntimeException('Integrasi OCIA belum dikonfigurasi atau tidak aktif untuk tenant ini.');
        }

        $po->loadMissing(['outlet', 'requestedBy', 'items.item', 'items.unit']);

        $path = (string) (data_get($profile->meta, 'order_path') ?: '/api/fbi/orders');
        $response = $this->client($profile)->post($this->url($profile, $path), $this->payload($po));

        if (! $response->successful()) {
            throw new RuntimeException(
                'OCIA merespons HTTP '.$response->status().': '.($response->json('message') ?? $response->body())
            );
        }

        $body = $response->json();

        return [
            'ocia_order_id'     => data_get($body, 'ocia_order_id'),
            'ocia_order_number' => data_get($body, 'ocia_order_number'),
            'duplicate'         => (bool) data_get($body, 'duplicate', false),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(PurchaseOrder $po): array
    {
        return [
            'order_no'           => $po->po_number,
            'external_po_id'     => $po->id,
            'outlet_code'        => $po->outlet?->code,
            'outlet_name'        => $po->outlet?->name,
            'requested_date'     => optional($po->needed_at)->toDateString() ?: now()->toDateString(),
            'needed_at'          => optional($po->needed_at)->toDateString(),
            'requested_by_name'  => $po->requestedBy?->name,
            'notes'              => $po->notes,
            'items'              => $po->items->map(fn ($item) => [
                'external_item_id' => $item->item_id,
                'sku'              => $item->item?->canonical_sku,
                'qty'              => (float) $item->qty_ordered,
                'uom'              => $item->unit?->code,
                'notes'            => $item->notes,
            ])->values()->all(),
        ];
    }

    private function client(IntegrationProfile $profile): PendingRequest
    {
        $client = Http::timeout((int) data_get($profile->meta, 'timeout_seconds', 10))->acceptJson();
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

    private function url(IntegrationProfile $profile, string $path): string
    {
        return rtrim($profile->base_url, '/').'/'.ltrim($path ?: '/', '/');
    }
}
