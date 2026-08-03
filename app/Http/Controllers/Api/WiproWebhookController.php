<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Core\Models\IntegrationProfile;
use App\Modules\Procurement\Models\PurchaseOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Inbound webhooks called BY Wipro (the reverse direction of
 * WiproIntegrationService, which calls OUT to Wipro). Wipro doesn't know a
 * tenant_id up front, so the order is looked up by its Wipro-assigned number
 * first (across all tenants), and the caller's bearer token is validated
 * against that specific PO's own tenant's IntegrationProfile — this avoids
 * needing tenant_id in the URL/payload while still scoping auth per tenant.
 */
class WiproWebhookController extends Controller
{
    public function dispatchNotification(Request $request): JsonResponse
    {
        $wiproOrderNumber = trim((string) $request->input('wipro_order_number', ''));

        if ($wiproOrderNumber === '') {
            return response()->json(['success' => false, 'message' => 'wipro_order_number wajib diisi.'], 400);
        }

        $po = PurchaseOrder::withoutGlobalScopes()
            ->where('external_reference', $wiproOrderNumber)
            ->first();

        if (! $po) {
            return response()->json(['success' => false, 'message' => "PO dengan wipro_order_number {$wiproOrderNumber} tidak ditemukan."], 404);
        }

        $profile = IntegrationProfile::query()
            ->where('tenant_id', $po->tenant_id)
            ->where('code', 'WIPRO')
            ->where('is_active', true)
            ->first();

        $expectedToken = $profile?->auth_token ?: $profile?->api_token;
        $header = $request->header('Authorization', '');
        $token = str_starts_with($header, 'Bearer ') ? substr($header, 7) : $header;

        if (! $expectedToken || $token !== $expectedToken) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 401);
        }

        $previousStatus = $po->status;

        $po->forceFill([
            'status'                => PurchaseOrder::STATUS_SHIPPED,
            'wipro_shipped_at'      => $request->input('shipped_at') ?: now(),
            'wipro_dispatch_number' => $request->input('dispatch_number'),
        ])->save();

        Log::info('[WIPRO] dispatch-notification received', [
            'po'              => $po->po_number,
            'previous_status' => $previousStatus,
            'dispatch_number' => $request->input('dispatch_number'),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Notifikasi pengiriman diterima. Status PO diperbarui ke SHIPPED.',
            'po_number' => $po->po_number,
        ]);
    }
}
