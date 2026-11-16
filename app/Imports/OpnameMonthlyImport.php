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
 * Import grid Opname Bulanan: baris = item, kolom = 4 sub-kolom
 * (IN|OUT|SPOIL|BAL) per tanggal. Dibaca lewat ToCollection manual (BUKAN
 * WithHeadingRow) karena jumlah grup tanggal dinamis per bulan --
 * WithHeadingRow akan memaksa semua header lewat formatter global ('slug'),
 * yang akan merusak header tanggal/sub-label.
 *
 * Makna tiap sub-kolom (lihat OpnameMonthlyImportTemplate untuk konteks
 * lengkap): HANYA BAL yang menyentuh ledger stok (lewat
 * OpnameService::updateItem() di processDate(), persis seperti BALANCE
 * tunggal yang ada sebelum fitur ini). IN/OUT/SPOIL murni arsip laporan
 * outlet (opname_items.reported_in_qty/out_qty/spoil_qty) -- TIDAK PERNAH
 * membentuk mutasi stok apa pun, independen dari status sesi (bisa
 * dikoreksi kapan pun lewat upload ulang, bahkan untuk tanggal yang
 * sesinya sudah PROCESSED -- beda dari BAL yang terkunci permanen begitu
 * PROCESSED).
 *
 * Sheet PETUNJUK sengaja TIDAK dimasukkan ke sheets() -- WithMultipleSheets
 * cuma memproses sheet yang namanya ada di map ini, sheet lain diabaikan.
 */
class OpnameMonthlyImport implements WithMultipleSheets
{
    private const SUB_LABELS = ['in', 'out', 'spoil', 'balance'];

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
     * Deteksi dini kalau file yang diupload format LAMA (template sebelum
     * fitur IN/OUT/SPOIL/BAL -- masih ada kolom "Kategori", header 1 baris,
     * 1 kolom per tanggal) atau rusak/salah sheet -- supaya gagal dengan
     * pesan JELAS, bukan diam-diam menghasilkan preview kosong/ngaco karena
     * kolom ke-geser (lihat laporan user 2026-11-16: file lama ke-upload ke
     * importer baru, preview-nya kosong tanpa penjelasan).
     */
    public function validateFormat(): ?string
    {
        $rows = $this->grid->rows ?? collect();

        if ($rows->count() < 2) {
            return 'File tidak terbaca sebagai template Opname Bulanan (sheet "OPNAME_BULANAN" kosong atau formatnya tidak sesuai).';
        }

        $headerRow2First = strtolower(trim((string) ($rows->get(1)[0] ?? '')));

        if ($headerRow2First !== 'item') {
            return 'Format file ini sepertinya dari template LAMA (sebelum kolom IN/OUT/SPOIL ditambahkan) atau rusak. Silakan download template BARU dulu (tombol "Download Template"), lalu pindahkan angka yang sudah Anda isi ke template baru itu.';
        }

        return null;
    }

    /**
     * @return array{dates: list<string>, items: list<array{sku: string, satuan: string, qty_by_date: array<string, array{in: ?string, out: ?string, spoil: ?string, balance: ?string}>}>}
     */
    public function parseGrid(): array
    {
        $rows = $this->grid->rows ?? collect();

        if ($rows->count() < 2) {
            return ['dates' => [], 'items' => []];
        }

        $headerRow1 = $rows->get(0);
        $dateGroups = []; // startColumnIndex => date

        for ($index = 3; $index < count($headerRow1); $index += 4) {
            $date = $this->normalizeDateHeader($headerRow1[$index] ?? null);

            if ($date !== null) {
                $dateGroups[$index] = $date;
            }
        }

        $items = [];

        // Baris pemisah kategori (kolom "Item"/SKU kosong) ikut dilewati di
        // sini secara alami, sama seperti baris benar-benar kosong.
        foreach ($rows->skip(2) as $row) {
            $sku = trim((string) ($row[1] ?? ''));

            if ($sku === '') {
                continue;
            }

            $satuan = trim((string) ($row[2] ?? ''));
            $qtyByDate = [];

            foreach ($dateGroups as $startIndex => $date) {
                $entry = [];
                foreach (self::SUB_LABELS as $offset => $subLabel) {
                    $raw = $row[$startIndex + $offset] ?? null;
                    $entry[$subLabel] = ($raw === null || trim((string) $raw) === '')
                        ? null
                        : Decimal::toFixed(trim((string) $raw), 6);
                }

                if ($entry['in'] === null && $entry['out'] === null && $entry['spoil'] === null && $entry['balance'] === null) {
                    continue;
                }

                $qtyByDate[$date] = $entry;
            }

            if (empty($qtyByDate)) {
                continue;
            }

            $items[] = ['sku' => $sku, 'satuan' => $satuan, 'qty_by_date' => $qtyByDate];
        }

        return ['dates' => array_values($dateGroups), 'items' => $items];
    }

    /**
     * Untuk tiap tanggal yang punya minimal 1 sel terisi, tentukan apakah
     * akan jadi sesi baru, menimpa sesi draft existing, atau BAL-nya terkunci
     * (sesi sudah diproses -- tetap TERMASUK dalam hasil supaya IN/OUT/SPOIL
     * masih bisa dikoreksi, lihat reported_fields_updatable).
     *
     * @return list<array{date: string, row_status: string, session_status: string|null, reported_fields_updatable: bool, items: list<array<string, mixed>>}>
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

            foreach ($itemRow['qty_by_date'] as $date => $entry) {
                $byDate[$date][] = array_merge($entry, [
                    'item_id' => (int) $item->id,
                    'item_name' => $item->name,
                    'sku' => $item->canonical_sku,
                    'satuan' => $itemRow['satuan'],
                ]);
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
                // IN/OUT/SPOIL bisa dikoreksi selama ADA sesi utk tanggal ini
                // (DRAFT atau PROCESSED) -- cuma tanggal yang BENAR-BENAR
                // belum punya sesi sama sekali DAN tidak ada BAL yang bisa
                // membuat sesi baru (lihat processDate()) yang tidak berguna
                // dipilih.
                'reported_fields_updatable' => $session !== null || collect($itemsForDate)->contains(fn ($r) => $r['balance'] !== null),
                'items' => $itemsForDate,
            ];
        }

        usort($results, fn ($a, $b) => strcmp($a['date'], $b['date']));

        return $results;
    }

    /**
     * Dipanggil per tanggal (SUDAH diurutkan ascending oleh pemanggil).
     * IN/OUT/SPOIL (opname_items.reported_*_qty) di-update TANPA syarat
     * status sesi -- murni informasional. BAL cuma diterapkan (lewat
     * updateItem()+submit()+approve(), identik perilaku sebelum fitur ini)
     * kalau sesi masih DRAFT (atau belum ada sama sekali, dibuat baru) DAN
     * item itu memang mengirim nilai BAL -- kalau sesi sudah BUKAN DRAFT,
     * bagian ini dilewati diam-diam (bukan exception) supaya tanggal itu
     * tetap bisa diproses utk koreksi IN/OUT/SPOIL-nya saja.
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

        $hasAnyBalance = collect($itemsForDate)->contains(fn ($row) => $row['balance'] !== null);

        if (! $session) {
            if (! $hasAnyBalance) {
                // Tidak ada sesi utk ditempeli catatan IN/OUT/SPOIL, dan
                // tidak ada BAL utk membuat sesi baru -- tidak ada yang bisa
                // dilakukan utk tanggal ini.
                return;
            }

            $session = $service->startSession([
                'tenant_id' => $tenantId,
                'outlet_id' => $outletId,
                'department_id' => $departmentId,
                'opname_date' => $date,
                'type' => OpnameSession::TYPE_DAILY,
                'notes' => 'Import bulanan',
            ], $userId);
        }

        $balanceLocked = $session->status !== OpnameSession::STATUS_DRAFT;

        $session->load(['items.item.inventoryUnit', 'items.item.baseUnit']);

        foreach ($itemsForDate as $row) {
            $opnameItem = $session->items->firstWhere('item_id', $row['item_id']);

            if (! $opnameItem) {
                continue;
            }

            $reportedUpdates = [];
            if ($row['in'] !== null) {
                $reportedUpdates['reported_in_qty'] = $row['in'];
            }
            if ($row['out'] !== null) {
                $reportedUpdates['reported_out_qty'] = $row['out'];
            }
            if ($row['spoil'] !== null) {
                $reportedUpdates['reported_spoil_qty'] = $row['spoil'];
            }
            if ($reportedUpdates !== []) {
                $opnameItem->update($reportedUpdates);
            }

            if ($balanceLocked || $row['balance'] === null) {
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

            $whole = $isInventoryUnit ? $row['balance'] : '0';
            $loose = $isInventoryUnit ? '0' : $row['balance'];

            $service->updateItem($opnameItem, $whole, $loose);
        }

        if ($balanceLocked || ! $hasAnyBalance) {
            return;
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
