<?php

namespace App\Imports;

use App\Modules\Inventory\Models\Item;
use App\Modules\Operations\Models\SpoilWaste;
use App\Services\SpoilWasteService;
use App\Services\StockLedgerService;
use App\Support\Decimal;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Throwable;

/**
 * Import grid Spoil & Waste Bulanan: baris = item, kolom = tanggal, 1
 * "Kategori Alasan (default)" per baris untuk seluruh bulan. Pola
 * ToCollection manual sama seperti OpnameMonthlyImport -- lihat docblock
 * di sana untuk alasannya.
 */
class SpoilWasteMonthlyImport implements WithMultipleSheets
{
    /** Keterangan detail kalau kolom KET tanggal itu kosong (& format lama). */
    private const DEFAULT_DETAIL = 'Import bulanan';

    private SpoilWasteMonthlyGridSheetImport $grid;

    public function __construct()
    {
        $this->grid = new SpoilWasteMonthlyGridSheetImport();
    }

    /**
     * @return array<string, object>
     */
    public function sheets(): array
    {
        return ['SPOIL_BULANAN' => $this->grid];
    }

    /**
     * Dua format diterima:
     * - BARU: header 2 baris, tiap tanggal 3 sub-kolom QTY | ALASAN | KET
     *   (baris 2 kolom E berisi "QTY"). Data mulai baris 3.
     * - LAMA: 1 kolom QTY per tanggal, header 1 baris -- file yang sudah
     *   telanjur diisi tim tetap bisa diupload.
     * ALASAN kosong = pakai "Kategori Alasan (default)" di kolom D.
     *
     * @return array{dates: list<string>, items: list<array{sku: string, satuan: string, cells: array<string, array{qty: string, reason_label: string, note: ?string}>}>}
     */
    public function parseGrid(): array
    {
        $rows = $this->grid->rows ?? collect();

        if ($rows->isEmpty()) {
            return ['dates' => [], 'items' => []];
        }

        $header = $rows->first();
        $subHeader = $rows->get(1);
        $isPerDateReason = strtoupper(trim((string) ($subHeader[4] ?? ''))) === 'QTY';

        // index kolom QTY => tanggal
        $dateColumns = [];

        foreach ($header as $index => $value) {
            if ($index < 4) {
                continue;
            }

            if ($isPerDateReason && strtoupper(trim((string) ($subHeader[$index] ?? ''))) !== 'QTY') {
                continue;
            }

            $date = $this->normalizeDateHeader($value);

            if ($date !== null) {
                $dateColumns[$index] = $date;
            }
        }

        $items = [];

        // Baris pemisah kategori (kolom "Item"/SKU kosong) ikut dilewati di
        // sini secara alami, sama seperti baris benar-benar kosong.
        foreach ($rows->skip($isPerDateReason ? 2 : 1) as $row) {
            $sku = trim((string) ($row[1] ?? ''));

            if ($sku === '') {
                continue;
            }

            $satuan = trim((string) ($row[2] ?? ''));
            $defaultReason = trim((string) ($row[3] ?? ''));
            $cells = [];

            foreach ($dateColumns as $index => $date) {
                $raw = $row[$index] ?? null;

                if ($raw === null || trim((string) $raw) === '') {
                    continue;
                }

                $reason = $isPerDateReason ? trim((string) ($row[$index + 1] ?? '')) : '';
                $note = $isPerDateReason ? trim((string) ($row[$index + 2] ?? '')) : '';

                $cells[$date] = [
                    'qty' => Decimal::toFixed(trim((string) $raw), 6),
                    'reason_label' => $reason !== '' ? $reason : $defaultReason,
                    'note' => $note !== '' ? $note : null,
                ];
            }

            if (empty($cells)) {
                continue;
            }

            $items[] = ['sku' => $sku, 'satuan' => $satuan, 'cells' => $cells];
        }

        return ['dates' => array_values($dateColumns), 'items' => $items];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function preview(int $tenantId, int $outletId, ?int $departmentId): array
    {
        $grid = $this->parseGrid();
        $cells = [];

        foreach ($grid['items'] as $itemRow) {
            $item = Item::query()
                ->where('tenant_id', $tenantId)
                ->where('canonical_sku', $itemRow['sku'])
                ->with(['inventoryUnit', 'baseUnit'])
                ->first();

            if (! $item) {
                continue;
            }

            foreach ($itemRow['cells'] as $date => $cellData) {
                $reasonCategory = $this->reasonCategory($cellData['reason_label']);
                $reasonDetail = $cellData['note'] ?? self::DEFAULT_DETAIL;
                [$qtyWhole, $qtyLoose, $qtyBase] = $this->splitQty($item, $itemRow['satuan'], $cellData['qty']);

                $existing = SpoilWaste::query()
                    ->where('tenant_id', $tenantId)
                    ->where('outlet_id', $outletId)
                    ->where('department_id', $departmentId)
                    ->where('item_id', $item->id)
                    ->whereDate('recorded_date', $date)
                    ->first();

                $rowStatus = 'creatable';

                if ($existing) {
                    $sameQty = bccomp((string) $existing->qty_in_base_unit, $qtyBase, 6) === 0;
                    $sameReason = $existing->reason_category === $reasonCategory;
                    // KET kosong di file = "tidak mengubah keterangan", bukan beda.
                    $sameDetail = $cellData['note'] === null || (string) $existing->reason_detail === $cellData['note'];

                    if ($sameQty && $sameReason && $sameDetail) {
                        $rowStatus = 'unchanged';
                    } else {
                        $rowStatus = $existing->mutation_id === null ? 'update_unposted' : 'update_posted';
                    }
                }

                $cells[] = [
                    'date' => $date,
                    'item_id' => (int) $item->id,
                    'item_name' => $item->name,
                    'sku' => $item->canonical_sku,
                    'satuan' => $itemRow['satuan'],
                    'reason_category' => $reasonCategory,
                    'reason_label' => $cellData['reason_label'],
                    'reason_detail' => $reasonDetail,
                    'note' => $cellData['note'],
                    'qty_whole' => $qtyWhole,
                    'qty_loose' => $qtyLoose,
                    'qty_in_base_unit' => $qtyBase,
                    'row_status' => $rowStatus,
                    'existing_qty_in_base_unit' => $existing?->qty_in_base_unit !== null ? (string) $existing->qty_in_base_unit : null,
                    'existing_reason_category' => $existing?->reason_category,
                ];
            }
        }

        usort($cells, fn ($a, $b) => $a['date'] <=> $b['date']);

        return $cells;
    }

    /**
     * Dipanggil per sel (SUDAH diurutkan ascending oleh pemanggil).
     */
    public function processCell(
        array $cell,
        int $tenantId,
        int $outletId,
        ?int $departmentId,
        int $userId,
        SpoilWasteService $service,
        StockLedgerService $ledger,
    ): void {
        if ($cell['reason_category'] === null) {
            throw new \RuntimeException("Kategori alasan \"{$cell['reason_label']}\" untuk item \"{$cell['item_name']}\" tidak dikenali.");
        }

        $item = Item::query()->where('tenant_id', $tenantId)->findOrFail($cell['item_id']);

        $existing = SpoilWaste::query()
            ->where('tenant_id', $tenantId)
            ->where('outlet_id', $outletId)
            ->where('department_id', $departmentId)
            ->where('item_id', $cell['item_id'])
            ->whereDate('recorded_date', $cell['date'])
            ->first();

        $fields = [
            'unit_id' => (int) $item->base_unit_id,
            'qty' => $cell['qty_in_base_unit'],
            'qty_whole' => $cell['qty_whole'],
            'qty_loose' => $cell['qty_loose'],
            'qty_in_base_unit' => $cell['qty_in_base_unit'],
            'reason_category' => $cell['reason_category'],
        ];

        // KET diisi -> pakai itu. KET kosong -> keterangan record lama
        // (mis. yang ditulis staf lewat form) tidak ditimpa; record baru
        // dapat "Import bulanan".
        $detail = ($cell['note'] ?? null) ?? $existing?->reason_detail ?? self::DEFAULT_DETAIL;
        $fields['reason_detail'] = $detail;
        $fields['notes'] = $detail;

        if ($existing && $existing->mutation_id !== null) {
            DB::transaction(function () use ($existing, $fields, $userId, $service, $ledger): void {
                $ledger->voidMutation($existing->mutation_id, [
                    'reference_type' => SpoilWaste::class,
                    'reference_id' => $existing->id,
                    'performed_by' => $userId,
                    'void_reason' => 'Dikoreksi lewat import bulanan',
                    'notes' => 'Dikoreksi lewat import bulanan',
                ]);

                $existing->forceFill(array_merge($fields, [
                    'mutation_id' => null,
                    'status' => SpoilWaste::STATUS_PENDING,
                    'approval_status' => SpoilWaste::STATUS_PENDING,
                ]))->save();

                $service->approve($existing->fresh(), $userId);
            });

            return;
        }

        if ($existing) {
            // Reset ke PENDING eksplisit -- existing bisa saja REJECTED
            // (mutation_id tetap null kalau ditolak sebelum pernah
            // di-approve), dan approve() cuma menerima status PENDING.
            $existing->forceFill(array_merge($fields, [
                'status' => SpoilWaste::STATUS_PENDING,
                'approval_status' => SpoilWaste::STATUS_PENDING,
            ]))->save();
            $service->approve($existing->fresh(), $userId);

            return;
        }

        $spoil = $service->record(array_merge($fields, [
            'tenant_id' => $tenantId,
            'outlet_id' => $outletId,
            'department_id' => $departmentId,
            'item_id' => $cell['item_id'],
            'recorded_date' => $cell['date'],
            'recorded_at' => $cell['date'],
        ]), $userId);

        $service->approve($spoil, $userId);
    }

    /**
     * @return array{0: string, 1: string, 2: string} qty_whole, qty_loose, qty_in_base_unit
     */
    private function splitQty(Item $item, string $satuan, string $qty): array
    {
        // base_unit_id WAJIB dicek duluan -- lihat catatan yang sama di
        // OpnameMonthlyImport::processDate().
        $isInventoryUnit = $satuan !== ''
            && $item->inventoryUnit
            && (int) $item->inventory_unit_id !== (int) $item->base_unit_id
            && strcasecmp((string) $item->inventoryUnit->abbreviation, $satuan) === 0;

        $qtyWhole = $isInventoryUnit ? $qty : '0.000000';
        $qtyLoose = $isInventoryUnit ? '0.000000' : $qty;
        $ratio = Decimal::toFixed($item->inventory_ratio ?: '1', 6);
        $qtyBase = bcadd(bcmul($qtyWhole, $ratio, 6), $qtyLoose, 6);

        return [$qtyWhole, $qtyLoose, $qtyBase];
    }

    private function reasonCategory(string $label): ?string
    {
        $label = trim($label);
        $normalized = strtoupper($label);

        if (in_array($normalized, [
            SpoilWaste::REASON_EXPIRED, SpoilWaste::REASON_RUSAK, SpoilWaste::REASON_KESALAHAN_PRODUKSI,
            SpoilWaste::REASON_TUMPAH, SpoilWaste::REASON_QUALITY_REJECT, SpoilWaste::REASON_LAINNYA,
        ], true)) {
            return $normalized;
        }

        $map = array_flip(array_map('strtolower', $this->reasonLabels()));

        return $map[strtolower($label)] ?? null;
    }

    /**
     * @return array<string, string>
     */
    private function reasonLabels(): array
    {
        return [
            SpoilWaste::REASON_EXPIRED => 'Kadaluarsa',
            SpoilWaste::REASON_RUSAK => 'Rusak/Cacat',
            SpoilWaste::REASON_KESALAHAN_PRODUKSI => 'Kesalahan Produksi',
            SpoilWaste::REASON_TUMPAH => 'Tumpah',
            SpoilWaste::REASON_QUALITY_REJECT => 'Reject Kualitas',
            SpoilWaste::REASON_LAINNYA => 'Lainnya',
        ];
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

class SpoilWasteMonthlyGridSheetImport implements ToCollection
{
    public ?Collection $rows = null;

    public function collection(Collection $rows): void
    {
        $this->rows = $rows;
    }
}
