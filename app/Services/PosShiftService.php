<?php

namespace App\Services;

use App\Modules\Pos\Models\PosPayment;
use App\Modules\Pos\Models\PosShift;
use App\Support\Decimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PosShiftService
{
    public function findOpenShift(int $tenantId, int $outletId): ?PosShift
    {
        return PosShift::query()
            ->where('tenant_id', $tenantId)
            ->where('outlet_id', $outletId)
            ->where('status', PosShift::STATUS_OPEN)
            ->with('openedBy')
            ->latest('opened_at')
            ->first();
    }

    /**
     * @param  array{opening_cash: mixed, notes?: string|null}  $data
     */
    public function openShift(array $data, int $tenantId, int $outletId, int $userId): PosShift
    {
        return DB::transaction(function () use ($data, $tenantId, $outletId, $userId): PosShift {
            $existing = PosShift::query()
                ->where('tenant_id', $tenantId)
                ->where('outlet_id', $outletId)
                ->where('status', PosShift::STATUS_OPEN)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                throw ValidationException::withMessages([
                    'outlet_id' => 'Sudah ada shift kasir yang berjalan untuk outlet ini.',
                ]);
            }

            return PosShift::query()->create([
                'tenant_id' => $tenantId,
                'outlet_id' => $outletId,
                'status' => PosShift::STATUS_OPEN,
                'opening_cash' => Decimal::toFixed($data['opening_cash'] ?? 0, 4),
                'opened_by' => $userId,
                'opened_at' => now(),
                'notes' => $data['notes'] ?? null,
            ]);
        });
    }

    /**
     * @param  array{closing_cash_actual: mixed, notes?: string|null}  $data
     */
    public function closeShift(PosShift $shift, array $data, int $userId): PosShift
    {
        return DB::transaction(function () use ($shift, $data, $userId): PosShift {
            $shift = PosShift::query()->lockForUpdate()->findOrFail($shift->id);

            if (! $shift->canClose()) {
                throw ValidationException::withMessages([
                    'status' => 'Shift ini sudah ditutup/direkonsiliasi.',
                ]);
            }

            $totalCash = $shift->payments()->where('method', PosPayment::METHOD_CASH)->sum('amount');
            $expected = bcadd((string) $shift->opening_cash, (string) $totalCash, 4);
            $actual = Decimal::toFixed($data['closing_cash_actual'], 4);
            $variance = bcsub($actual, $expected, 4);

            $shift->update([
                'status' => PosShift::STATUS_CLOSED,
                'closing_cash_expected' => $expected,
                'closing_cash_actual' => $actual,
                'variance' => $variance,
                'closed_by' => $userId,
                'closed_at' => now(),
                'notes' => $data['notes'] ?? $shift->notes,
            ]);

            return $shift->refresh();
        });
    }

    /**
     * @param  array{notes?: string|null}  $data
     */
    public function reconcile(PosShift $shift, array $data, int $userId): PosShift
    {
        return DB::transaction(function () use ($shift, $data, $userId): PosShift {
            $shift = PosShift::query()->lockForUpdate()->findOrFail($shift->id);

            if (! $shift->canReconcile()) {
                throw ValidationException::withMessages([
                    'status' => 'Shift ini belum ditutup atau sudah direkonsiliasi.',
                ]);
            }

            $shift->update([
                'status' => PosShift::STATUS_RECONCILED,
                'reconciled_by' => $userId,
                'reconciled_at' => now(),
                'notes' => $data['notes'] ?? $shift->notes,
            ]);

            return $shift->refresh();
        });
    }
}
