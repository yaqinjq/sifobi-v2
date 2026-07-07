<?php

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\Controller;
use App\Modules\Inventory\Models\Item;
use App\Modules\Inventory\Models\UnitConversion;
use App\Support\Decimal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ItemConversionController extends Controller
{
    public function store(Request $request, Item $item): JsonResponse
    {
        $tenantId = $this->tenantId($request);
        $this->authorizeTenant($request, $item);

        $validated = $request->validate([
            'from_unit_id' => ['required', 'integer', Rule::exists('units', 'id')->where('tenant_id', $tenantId)],
            'to_unit_id' => ['required', 'integer', 'different:from_unit_id', Rule::exists('units', 'id')->where('tenant_id', $tenantId)],
            'factor' => ['required', Decimal::validationRule(8), function (string $attribute, mixed $value, \Closure $fail): void {
                try {
                    $normalized = Decimal::normalize($value, 8);
                } catch (\InvalidArgumentException) {
                    return;
                }

                if ((float) $normalized < 0.00000001) {
                    $fail("The {$attribute} field must be greater than zero.");
                }
            }],
        ]);

        $factor = Decimal::toFixed($validated['factor'], 8);

        $conversion = DB::transaction(fn () => UnitConversion::query()->updateOrCreate(
            [
                'tenant_id' => $tenantId,
                'item_id' => $item->id,
                'from_unit_id' => (int) $validated['from_unit_id'],
                'to_unit_id' => (int) $validated['to_unit_id'],
            ],
            [
                'multiply_rate' => $factor,
                'factor' => $factor,
            ]
        ));

        $conversion->load(['fromUnit', 'toUnit']);

        return response()->json([
            'success' => true,
            'conversion' => $this->payload($conversion),
        ]);
    }

    public function destroy(Request $request, Item $item, UnitConversion $conversion): JsonResponse
    {
        $this->authorizeTenant($request, $item);
        abort_unless($conversion->tenant_id === $item->tenant_id && $conversion->item_id === $item->id, 404);

        DB::transaction(fn () => $conversion->delete());

        return response()->json(['success' => true]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(UnitConversion $conversion): array
    {
        return [
            'id' => $conversion->id,
            'from_unit' => $conversion->fromUnit?->code,
            'to_unit' => $conversion->toUnit?->code,
            'factor' => rtrim(rtrim((string) ($conversion->factor ?? $conversion->multiply_rate), '0'), '.'),
            'destroy_url' => route('master-data.items.conversions.destroy', [$conversion->item_id, $conversion]),
        ];
    }

    private function tenantId(Request $request): int
    {
        $tenantId = $request->user()?->tenant_id;

        abort_unless($tenantId, 403, 'Tenant belum terpasang pada user.');

        return (int) $tenantId;
    }

    private function authorizeTenant(Request $request, Item $item): void
    {
        abort_unless($item->tenant_id === $this->tenantId($request), 404);
    }
}
