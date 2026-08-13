<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Core\Models\IntegrationProfile;
use App\Modules\Core\Models\Outlet;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Inbound outlet-list pull, called BY Wipro to sync its outlet mapping.
 * Wipro doesn't know a tenant_id up front, so the caller's bearer token is
 * matched against every active WIPRO IntegrationProfile to resolve the tenant
 * (same shared secret already used by WiproWebhookController and by Wipro's
 * own outbound dispatch-notification call).
 */
class WiproOutletController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $header = $request->header('Authorization', '');
        $token  = str_starts_with($header, 'Bearer ') ? substr($header, 7) : $header;

        $profile = IntegrationProfile::withoutGlobalScopes()
            ->where('code', 'WIPRO')
            ->where('is_active', true)
            ->get()
            ->first(function (IntegrationProfile $profile) use ($token) {
                $expected = $profile->auth_token ?: $profile->api_token;

                return $expected && hash_equals($expected, $token);
            });

        if (! $profile) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 401);
        }

        $outlets = Outlet::withoutGlobalScopes()
            ->where('tenant_id', $profile->tenant_id)
            ->where('status', 'ACTIVE')
            ->with('brand')
            ->orderBy('name')
            ->get()
            ->map(fn (Outlet $outlet) => [
                'code'   => $outlet->code,
                'name'   => $outlet->name,
                'brand'  => $outlet->brand?->name,
                'status' => $outlet->status,
            ]);

        return response()->json(['data' => $outlets]);
    }
}
