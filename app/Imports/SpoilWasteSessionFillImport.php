<?php

namespace App\Imports;

use App\Services\SpoilWasteService;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Throwable;

/**
 * Importer "Isi Massal lewat Excel" untuk Spoil & Waste -- padanan
 * OpnameSessionFillImport. Setiap baris terisi (STOK > 0) memanggil
 * SpoilWasteService::record() -- status tetap PENDING, approval manual
 * tetap berjalan seperti alur normal "Catat Spoil" (BUKAN auto-approve
 * seperti import flat/Bulanan yang sudah ada -- filosofinya setara isi
 * manual dipercepat, bukan bypass approval).
 */
class SpoilWasteSessionFillImport implements ToCollection, WithHeadingRow
{
    use Importable;

    private Collection $rows;

    public function collection(Collection $rows): void
    {
        $this->rows = $rows;
    }

    private const REASON_LABELS = [
        'KADALUARSA' => \App\Modules\Operations\Models\SpoilWaste::REASON_EXPIRED,
        'RUSAK/CACAT' => \App\Modules\Operations\Models\SpoilWaste::REASON_RUSAK,
        'KESALAHAN PRODUKSI' => \App\Modules\Operations\Models\SpoilWaste::REASON_KESALAHAN_PRODUKSI,
        'TUMPAH' => \App\Modules\Operations\Models\SpoilWaste::REASON_TUMPAH,
        'REJECT KUALITAS' => \App\Modules\Operations\Models\SpoilWaste::REASON_QUALITY_REJECT,
        'LAINNYA' => \App\Modules\Operations\Models\SpoilWaste::REASON_LAINNYA,
    ];

    /**
     * @param  Collection<int, \App\Modules\Inventory\Models\Item>  $eligibleItems  item yang sama dipakai generate template (scope outlet+departemen ini)
     * @return array{applied: int, skipped_blank: int, not_found: list<string>, errors: list<string>}
     */
    public function applyTo(
        int $tenantId,
        int $outletId,
        ?int $departmentId,
        int $userId,
        SpoilWasteService $service,
        Collection $eligibleItems,
    ): array {
        $bySku = [];
        foreach ($eligibleItems as $item) {
            $sku = strtolower(trim((string) $item->canonical_sku));
            if ($sku !== '') {
                $bySku[$sku] = $item;
            }
        }

        $applied = 0;
        $skippedBlank = 0;
        $notFound = [];
        $errors = [];

        foreach ($this->rows ?? collect() as $row) {
            $sku = strtolower(trim((string) ($row['kode_item'] ?? '')));
            if ($sku === '') {
                continue;
            }

            $stok = trim((string) ($row['stok'] ?? ''));
            if ($stok === '' || (float) $stok <= 0) {
                $skippedBlank++;
                continue;
            }

            $item = $bySku[$sku] ?? null;
            if (! $item) {
                $label = (string) ($row['item'] ?? $sku);
                $notFound[] = "{$label}: SKU tidak ditemukan untuk outlet/departemen ini";
                continue;
            }

            $reasonRaw = strtoupper(trim((string) ($row['kategori_alasan'] ?? '')));
            $reasonCategory = self::REASON_LABELS[$reasonRaw] ?? null;
            if ($reasonCategory === null) {
                $errors[] = "{$item->name}: Kategori Alasan wajib diisi (salah satu: ".implode(', ', array_keys(self::REASON_LABELS)).')';
                continue;
            }

            $tgl = trim((string) ($row['tgl'] ?? ''));

            // STOK diisi dalam satuan yang tertulis di kolom "Satuan" template
            // (inventoryUnit kalau ada, fallback baseUnit) -- pakai jalur qty+
            // unit_id lama di record() (BUKAN qty_whole/qty_loose) karena form
            // kertas cuma punya 1 angka per baris, bukan pecahan utuh/ecer.
            try {
                $service->record([
                    'tenant_id' => $tenantId,
                    'outlet_id' => $outletId,
                    'department_id' => $departmentId,
                    'item_id' => $item->id,
                    'unit_id' => (int) ($item->inventory_unit_id ?: $item->base_unit_id),
                    'qty' => $stok,
                    'reason_category' => $reasonCategory,
                    'reason_detail' => trim((string) ($row['note'] ?? '')) ?: null,
                    'recorded_date' => $tgl !== '' ? $tgl : now()->toDateString(),
                    'recorded_at' => $tgl !== '' ? $tgl : now(),
                ], $userId);
                $applied++;
            } catch (ValidationException $e) {
                throw $e;
            } catch (Throwable $e) {
                $errors[] = "{$item->name}: {$e->getMessage()}";
            }
        }

        return [
            'applied' => $applied,
            'skipped_blank' => $skippedBlank,
            'not_found' => $notFound,
            'errors' => $errors,
        ];
    }
}
