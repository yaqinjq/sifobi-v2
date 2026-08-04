<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Core\Models\IntegrationProfile;
use App\Modules\Procurement\Models\PurchaseOrder;
use App\Modules\Procurement\Models\PurchaseOrderShipment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Inbound webhooks called BY Wipro. Wipro doesn't know a tenant_id up front,
 * so the order is looked up by its Wipro-assigned number first, then the
 * caller's bearer token is validated against that tenant's IntegrationProfile.
 */
class WiproWebhookController extends Controller
{
    /**
     * Called once per box/DO that Wipro ships. A single PO may generate
     * multiple calls (1 per delivery box), each creating a new shipment record.
     * The PO status is set to SHIPPED on the first call and stays there.
     */
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
            return response()->json([
                'success' => false,
                'message' => "PO dengan wipro_order_number {$wiproOrderNumber} tidak ditemukan.",
            ], 404);
        }

        $profile = IntegrationProfile::query()
            ->where('tenant_id', $po->tenant_id)
            ->where('code', 'WIPRO')
            ->where('is_active', true)
            ->first();

        $expectedToken = $profile?->auth_token ?: $profile?->api_token;
        $header        = $request->header('Authorization', '');
        $token         = str_starts_with($header, 'Bearer ') ? substr($header, 7) : $header;

        if (! $expectedToken || $token !== $expectedToken) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 401);
        }

        $shippedAt = $request->input('shipped_at') ? now()->parse($request->input('shipped_at')) : now();

        // Create a shipment record for this specific DO/box
        $shipment = PurchaseOrderShipment::create([
            'tenant_id'          => $po->tenant_id,
            'purchase_order_id'  => $po->id,
            'do_number'          => $request->input('dispatch_number') ?: $request->input('do_number'),
            'invoice_number'     => $request->input('invoice_number'),
            'shipped_at'         => $shippedAt,
            'notes'              => $request->input('notes'),
        ]);

        $previousStatus = $po->status;

        // Update PO status + keep legacy columns pointing to the latest shipment
        $po->forceFill([
            'status'                => PurchaseOrder::STATUS_SHIPPED,
            'wipro_shipped_at'      => $shippedAt,
            'wipro_dispatch_number' => $shipment->do_number,
        ])->save();

        Log::info('[WIPRO] dispatch-notification received', [
            'po'              => $po->po_number,
            'shipment_id'     => $shipment->id,
            'do_number'       => $shipment->do_number,
            'invoice_number'  => $shipment->invoice_number,
            'previous_status' => $previousStatus,
            'is_first_shipment' => $previousStatus !== PurchaseOrder::STATUS_SHIPPED,
        ]);

        return response()->json([
            'success'     => true,
            'message'     => 'Notifikasi pengiriman diterima. Shipment #'.$shipment->id.' berhasil dicatat.',
            'po_number'   => $po->po_number,
            'shipment_id' => $shipment->id,
        ]);
    }
}
