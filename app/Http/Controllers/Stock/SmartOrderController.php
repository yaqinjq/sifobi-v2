<?php

namespace App\Http\Controllers\Stock;

use App\Http\Controllers\Controller;
use App\Services\SmartOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SmartOrderController extends Controller
{
    public function __construct(private readonly SmartOrderService $smartOrderService)
    {
    }

    public function suggest(Request $request): JsonResponse
    {
        $tenantId = (int) $request->user()->tenant_id;

        $validated = $request->validate([
            'item_id' => [
                'required',
                'integer',
                Rule::exists('items', 'id')->where('tenant_id', $tenantId),
            ],
            'outlet_id' => [
                'required',
                'integer',
                Rule::exists('outlets', 'id')->where('tenant_id', $tenantId),
            ],
        ]);

        return response()->json($this->smartOrderService->getSuggestion(
            (int) $validated['item_id'],
            (int) $validated['outlet_id'],
            $tenantId
        ));
    }
}
