<?php

namespace App\Imports;

use App\Modules\Inventory\Models\Item;
use App\Modules\Operations\Models\OpnameSession;
use App\Services\OpnameService;
use App\Support\Decimal;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Throwable;

/**
 * Import grid Opname Bulanan: baris = item, kolom = tanggal. Dibaca lewat
 * ToCollection manual (BUKAN WithHeadingRow) karena jumlah kolom tanggal
 * dinamis per bulan -- WithHeadingRow akan memaksa semua header lewat
 * formatter global ('slug'), yang akan merusak header tanggal.
 *
 * Sheet PETUNJUK sengaja TIDAK dimasukkan ke sheets() -- WithMultipleSheets
 * cuma memproses sheet yang namanya ada di map ini, sheet lain diabaikan.
 */
class OpnameMonthlyImport implements WithMultipleSheets
{
    private OpnameMonthlyGridSheetImport $grid;

    public function __construct()
    {
        $this->grid = new OpnameMonthlyGridSheetImport();
    }

    /**
     * @return array<string, object>
     */
    public function sheets(): array
    {
        return ['OPNAME_BULANAN' => $this->grid];
    }

    /**
     * @return array{dates: list<string>, items: list<array{sku: string, satuan: string, qty_by_date: array<string, string>}>}
     */
    public function parseGrid(): array
    {
        $rows = $this->grid->rows ?? collect();

        if ($rows->isEmpty()) {
            return ['dates' => [], 'items' => []];
        }

        $header = $rows->first();
        $dateColumns = [];

        foreach ($header as $index => $value) {
            if ($index < 4) {
                continue;
            }

            $date = $this->normalizeDateHeader($value);

            if ($date !== null) {
                $dateColumns[$index] = $date;
            }
        }

        $items = [];

        foreach ($rows->skip(1) as $row) {
            $sku = trim((string) ($row[2] ?? ''));

            if ($sku === '') {
                continue;
            }

            $satuan = trim((string) ($row[3] ?? ''));
            $qtyByDate = [];

            foreach ($dateColumns as $index => $date) {
                $raw = $row[$index] ?? null;

                if ($raw === null || trim((string) $raw) === '') {
                    continue;
                }

                $qtyByDate[$date] = Decimal::toFixed(trim((string) $raw), 6);
            }

            if (empty($qtyByDate)) {
                continue;
            }

            $items[] = ['sku' => $sku, 'satuan' => $satuan, 'qty_by_date' => $qtyByDate];
        }

        return ['dates' => array_values($dateColumns), 'items' => $items];
    }

    /**
     * Untuk tiap tanggal yang punya minimal 1 sel terisi, tentukan apakah
     * akan jadi sesi baru, menimpa sesi draft existing, atau dilewati
     * (sesi existing sudah submitted/processed -- di luar scope V1).
     *
     * @return list<array{date: string, row_status: string, session_status: string|null, items: list<array<string, mixed>>}>
     */
    public function preview(int $tenantId, int $outletId, ?int $departmentId): array
    {
        $grid = $this->parseGrid();
        $byDate = [];

        foreach ($grid['items'] as $itemRow) {
            $item = Item::query()
                ->where('tenant_id', $tenantId)
                ->where('canonical_sku', $itemRow['sku'])
                ->first();

            if (! $item) {
                continue;
            }

            foreach ($itemRow['qty_by_date'] as $date => $qty) {
                $byDate[$date][] = [
                    'item_id' => (int) $item->id,
                    'item_name' => $item->name,
                    'sku' => $item->canonical_sku,
                    'satuan' => $itemRow['satuan'],
                    'qty' => $qty,
                ];
            }
        }

        $results = [];

        foreach ($byDate as $date => $itemsForDate) {
            $session = OpnameSession::query()
                ->where('tenant_id', $tenantId)
                ->where('outlet_id', $outletId)
                ->where('department_id', $departmentId)
                ->whereDate('opname_date', $date)
                ->first();

            $rowStatus = 'new_session';

            if ($session) {
                $rowStatus = $session->status === OpnameSession::STATUS_DRAFT ? 'update_draft' : 'skip_locked';
            }

            $results[] = [
                'date' => $date,
                'row_status' => $rowStatus,
                'session_status' => $session?->status,
                'items' => $itemsForDate,
            ];
        }

        usort($results, fn ($a, $b) => strcmp($a['date'], $b['date']));

        return $results;
    }

    /**
     * Dipanggil per tanggal (SUDAH diurutkan ascending oleh pemanggil) --
     * buat/pakai ulang sesi draft, isi tiap item, submit, lalu approve
     * langsung. Melempar exception kalau sesi untuk tanggal ini sudah
     * submitted/processed (di luar scope V1 -- caller mengisolasi per
     * tanggal, tidak menghentikan tanggal lain).
     *
     * @param  list<array<string, mixed>>  $itemsForDate
     */
    public function processDate(
        string $date,
        array $itemsForDate,
        int $tenantId,
        int $outletId,
        ?int $departmentId,
        int $userId,
        OpnameService $service,
    ): void {
        $session = OpnameSession::query()
            ->where('tenant_id', $tenantId)
            ->where('outlet_id', $outletId)
            ->where('department_id', $departmentId)
            ->whereDate('opname_date', $date)
            ->first();

        if ($session && $session->status !== OpnameSession::STATUS_DRAFT) {
            throw new \RuntimeException("Tanggal {$date} sudah punya sesi opname berstatus {$session->status} -- dilewati, tidak bisa diubah lewat import ini.");
        }

        if (! $session) {
            $session = $service->startSession([
                'tenant_id' => $tenantId,
                'outlet_id' => $outletId,
                'department_id' => $departmentId,
                'opname_date' => $date,
                'type' => OpnameSession::TYPE_DAILY,
                'notes' => 'Import bulanan',
            ], $userId);
        }

        $session->load(['items.item.inventoryUnit', 'items.item.baseUnit']);

        foreach ($itemsForDate as $row) {
            $opnameItem = $session->items->firstWhere('item_id', $row['item_id']);

            if (! $opnameItem) {
                continue;
            }

            $item = $opnameItem->item;

            // base_unit_id WAJIB dicek duluan: banyak item punya
            // inventory_unit_id === base_unit_id (satuan inventory sama
            // dengan satuan dasar) -- kalau cuma cocokkan ke abbreviation
            // inventory_unit, item begini akan salah dikalikan
            // inventory_ratio padahal seharusnya 1:1 (pola yang sama
            // seperti perbaikan SpoilWasteService::calculateBaseQty()).
            $isInventoryUnit = $row['satuan'] !== ''
                && $item?->inventoryUnit
                && (int) $item->inventory_unit_id !== (int) $item->base_unit_id
                && strcasecmp((string) $item->inventoryUnit->abbreviation, (string) $row['satuan']) === 0;

            $whole = $isInventoryUnit ? $row['qty'] : '0';
            $loose = $isInventoryUnit ? '0' : $row['qty'];

            $service->updateItem($opnameItem, $whole, $loose);
        }

        $session->refresh();
        $service->submit($session, $userId);
        $service->approve($session, $userId);
    }

    private function normalizeDateHeader(mixed $value): ?string
    {
        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value)->format('Y-m-d');
        }

        if (is_numeric($value)) {
            try {
                return Carbon::instance(ExcelDate::excelToDateTimeObject((float) $value))->format('Y-m-d');
            } catch (Throwable) {
                return null;
            }
        }

        $str = trim((string) $value);

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $str) === 1) {
            return $str;
        }

        try {
            return Carbon::parse($str)->format('Y-m-d');
        } catch (Throwable) {
            return null;
        }
    }
}

class OpnameMonthlyGridSheetImport implements ToCollection
{
    public ?Collection $rows = null;

    public function collection(Collection $rows): void
    {
        $this->rows = $rows;
    }
}
