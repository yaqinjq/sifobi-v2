<?php

namespace App\Services;

use App\Modules\Operations\Models\InventoryCountMovement;
use App\Modules\Operations\Models\InventoryCountOpeningBalance;
use App\Support\Decimal;
use Illuminate\Support\Carbon;

/**
 * Rekap Mingguan -- saldo berjalan (BAL = saldo awal + IN - OUT) per item
 * per tanggal, dihitung dari inventory_count_movements +
 * inventory_count_opening_balances. SENGAJA TERISOLASI dari
 * stock_balances/stock_mutations (ledger resmi SIFOBI) -- lihat komentar
 * migration. Fitur ini TIDAK PERNAH memanggil StockLedgerService atau
 * menulis ke tabel ledger resmi. Semua tampilan saldo dari service ini
 * WAJIB dilabeli "saldo mandiri Inventory Simple, bukan saldo stok
 * sistem resmi" di view.
 *
 * Desain mengikuti prototipe HTML yang diadopsi user (form IN/OUT
 * mingguan): saldo awal minggu yang tidak diisi akan menelusuri saldo
 * akhir minggu sebelumnya (rekursif, dibatasi MAX_WEEKS_BACK) -- TIDAK
 * PERNAH default ke 0 secara diam-diam kalau penelusuran gagal
 * menemukan titik mulai yang jelas.
 */
class InventoryCountMovementService
{
    // Batas jarak penelusuran mundur (bukan jumlah query rekursif -- lihat
    // catatan di bawah, pencarian saldo awal sekarang 1 query, bukan
    // rekursi per-minggu yang sempat terbukti boros N query untuk item
    // yang genuinely belum pernah diisi saldo awal sama sekali).
    private const MAX_LOOKBACK_DAYS = 730; // ~2 tahun.

    public function weekStart(Carbon $date): Carbon
    {
        return $date->copy()->startOfWeek(Carbon::MONDAY)->startOfDay();
    }

    /**
     * @return array{error: ?string, base: ?string, days: array<int, array{date: string, qty_in: ?string, qty_out: ?string, prior_balance: ?string, balance: ?string, error: ?string}>}
     */
    public function calculateWeekBalances(
        int $tenantId,
        int $outletId,
        int $departmentId,
        int $itemId,
        Carbon $weekStart
    ): array {
        // Saldo awal minggu TARGET sendiri, kalau ada, dipakai langsung.
        // Kalau tidak, cari saldo awal TERDEKAT di minggu manapun sebelum
        // target (1 query, bukan rekursi mundur per-minggu) -- menelusuri
        // minggu demi minggu lewat N query terbukti boros untuk item yang
        // genuinely belum pernah diisi sama sekali (lihat riwayat commit).
        $opening = InventoryCountOpeningBalance::query()
            ->where('tenant_id', $tenantId)
            ->where('outlet_id', $outletId)
            ->where('department_id', $departmentId)
            ->where('item_id', $itemId)
            ->whereDate('week_start_date', '<=', $weekStart->toDateString())
            ->orderByDesc('week_start_date')
            ->first();

        if (! $opening) {
            return ['error' => 'Saldo awal belum tersedia untuk item ini -- isi saldo awal minggu sebelum mengisi IN/OUT.', 'base' => null, 'days' => []];
        }

        $openingWeekStart = Carbon::parse($opening->week_start_date);

        if ($openingWeekStart->diffInDays($weekStart) > self::MAX_LOOKBACK_DAYS) {
            return ['error' => 'Saldo awal terakhir terlalu jauh di belakang (lebih dari '.self::MAX_LOOKBACK_DAYS.' hari) -- isi saldo awal baru untuk minggu ini.', 'base' => null, 'days' => []];
        }

        // Ambil SEMUA pergerakan dari minggu saldo awal sampai akhir minggu
        // target dalam 1 query, lalu jalan maju hari-demi-hari di PHP --
        // jauh lebih murah daripada query per minggu/hari.
        $rangeEnd = $weekStart->copy()->addDays(6);
        $movements = InventoryCountMovement::query()
            ->where('tenant_id', $tenantId)
            ->where('outlet_id', $outletId)
            ->where('department_id', $departmentId)
            ->where('item_id', $itemId)
            ->whereBetween('movement_date', [$openingWeekStart->toDateString(), $rangeEnd->toDateString()])
            ->get()
            ->keyBy(fn ($m) => $m->movement_date->toDateString());

        $running = (string) $opening->qty;
        $error = null;
        $totalDays = (int) $openingWeekStart->diffInDays($rangeEnd);
        $targetWeekOffset = (int) $openingWeekStart->diffInDays($weekStart);
        // Saldo di AWAL minggu target (setelah menjalankan semua pergerakan
        // dari minggu saldo awal sampai sebelum minggu target) -- BUKAN
        // nilai saldo awal mentah yang tersimpan di DB kalau sumbernya dari
        // minggu yang lebih lama (lihat test "recurses into the previous
        // week automatically").
        $weekBase = $running;
        $days = [];

        for ($i = 0; $i <= $totalDays; $i++) {
            $date = $openingWeekStart->copy()->addDays($i);
            $movement = $movements->get($date->toDateString());
            $priorBalance = $running;

            if ($i === $targetWeekOffset) {
                $weekBase = $priorBalance;
            }

            if ($error === null) {
                $in = $movement?->qty_in !== null ? (string) $movement->qty_in : '0';
                $out = $movement?->qty_out !== null ? (string) $movement->qty_out : '0';
                $running = bcsub(bcadd($running, $in, 6), $out, 6);

                if (bccomp($running, '0', 6) < 0) {
                    $error = "Saldo negatif pada {$date->toDateString()}: {$running}";
                }
            }

            // Cuma simpan hari-hari di minggu TARGET untuk hasil akhir --
            // hari sebelumnya cuma dipakai menghitung saldo berjalan.
            if ($i >= $targetWeekOffset) {
                $days[] = [
                    'date' => $date->toDateString(),
                    'qty_in' => $movement?->qty_in !== null ? (string) $movement->qty_in : null,
                    'qty_out' => $movement?->qty_out !== null ? (string) $movement->qty_out : null,
                    'prior_balance' => $priorBalance,
                    'balance' => $error === null ? $running : null,
                    'error' => $error,
                ];
            }
        }

        return ['error' => $error, 'base' => $weekBase, 'days' => $days];
    }

    public function recordMovement(
        int $tenantId,
        int $outletId,
        int $departmentId,
        int $itemId,
        Carbon $date,
        ?string $qtyIn,
        ?string $qtyOut,
        int $userId
    ): InventoryCountMovement {
        return InventoryCountMovement::query()->updateOrCreate(
            [
                'tenant_id' => $tenantId,
                'outlet_id' => $outletId,
                'department_id' => $departmentId,
                'item_id' => $itemId,
                'movement_date' => $date->toDateString(),
            ],
            [
                'qty_in' => $qtyIn !== null && $qtyIn !== '' ? Decimal::toFixed($qtyIn) : null,
                'qty_out' => $qtyOut !== null && $qtyOut !== '' ? Decimal::toFixed($qtyOut) : null,
                'recorded_by' => $userId,
            ]
        );
    }

    public function recordOpeningBalance(
        int $tenantId,
        int $outletId,
        int $departmentId,
        int $itemId,
        Carbon $weekStart,
        string $qty,
        int $userId
    ): InventoryCountOpeningBalance {
        return InventoryCountOpeningBalance::query()->updateOrCreate(
            [
                'tenant_id' => $tenantId,
                'outlet_id' => $outletId,
                'department_id' => $departmentId,
                'item_id' => $itemId,
                'week_start_date' => $this->weekStart($weekStart)->toDateString(),
            ],
            [
                'qty' => Decimal::toFixed($qty),
                'recorded_by' => $userId,
            ]
        );
    }
}
