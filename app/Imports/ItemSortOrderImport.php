<?php

namespace App\Imports;

use App\Modules\Inventory\Models\Item;
use App\Modules\Inventory\Models\ItemDepartmentSortOrder;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use RuntimeException;
use Throwable;

/**
 * Import urutan ITEM di dalam sub kategori, untuk SATU departemen (dipilih
 * eksplisit lewat form upload, SAMA SEPERTI OpnameMonthlyImportTemplate --
 * tidak di-parsing dari isi file, supaya tidak rapuh). File-nya adalah
 * template yang diunduh dari ItemSortOrderController::template() (sudah
 * terisi SKU/Item/Kategori/Sub Kategori semua item departemen tsb), tim
 * tinggal mengisi kolom "Urutan" sesuai posisi di form fisik.
 *
 * Baris dengan Urutan kosong DILEWATI (bukan dianggap "hapus urutan lama"),
 * supaya file yang cuma diisi sebagian tetap aman diupload berkali-kali --
 * sama seperti kebijakan DepartmentCategoryMappingImport.
 */
class ItemSortOrderImport
{
    private int $processed = 0;

    /**
     * @var list<array{row:int,message:string}>
     */
    private array $rowErrors = [];

    public function __construct(
        private readonly int $tenantId,
        private readonly int $departmentId,
    ) {
    }

    public function import(string $filePath): void
    {
        $spreadsheet = IOFactory::load($filePath);
        $sheet = $spreadsheet->getActiveSheet();

        $columns = $this->detectColumns($sheet);

        if (! $columns) {
            $this->rowErrors[] = [
                'row' => 0,
                'message' => 'Format file tidak dikenali -- pastikan ada kolom "SKU" dan "Urutan" (gunakan template yang diunduh dari halaman ini).',
            ];

            return;
        }

        ['header' => $headerRow, 'sku' => $skuCol, 'urutan' => $urutanCol] = $columns;
        $highestRow = $sheet->getHighestDataRow();

        for ($rowNum = $headerRow + 1; $rowNum <= $highestRow; $rowNum++) {
            $sku = trim((string) $sheet->getCell("{$skuCol}{$rowNum}")->getValue());
            $urutan = trim((string) $sheet->getCell("{$urutanCol}{$rowNum}")->getValue());

            if ($sku === '' && $urutan === '') {
                continue;
            }

            if ($urutan === '') {
                continue; // belum diisi tim -- bukan error, biarkan urutan lama (kalau ada) apa adanya
            }

            try {
                $this->importRow($sku, $urutan);
                $this->processed++;
            } catch (Throwable $throwable) {
                $this->rowErrors[] = [
                    'row' => $rowNum,
                    'message' => $throwable->getMessage(),
                ];
            }
        }
    }

    /**
     * @return array{success:bool,processed:int,failed:int,errors:list<array{row:int,message:string}>}
     */
    public function summary(): array
    {
        return [
            'success' => count($this->rowErrors) === 0,
            'processed' => $this->processed,
            'failed' => count($this->rowErrors),
            'errors' => $this->rowErrors,
        ];
    }

    private function importRow(string $sku, string $urutan): void
    {
        if ($sku === '') {
            throw new RuntimeException('Kolom SKU kosong padahal Urutan diisi -- jangan hapus/ubah kolom SKU di template.');
        }

        if (! ctype_digit($urutan) || (int) $urutan <= 0) {
            throw new RuntimeException("Urutan '{$urutan}' harus berupa angka bulat positif.");
        }

        $item = Item::query()
            ->where('tenant_id', $this->tenantId)
            ->where('canonical_sku', $sku)
            ->first();

        if (! $item) {
            throw new RuntimeException("SKU '{$sku}' tidak ditemukan -- jangan ubah kolom SKU di template, unduh ulang kalau item sudah berubah.");
        }

        ItemDepartmentSortOrder::query()->updateOrCreate(
            ['item_id' => $item->id, 'department_id' => $this->departmentId],
            ['sort_order' => (int) $urutan]
        );
    }

    /**
     * @return array{header:int,sku:string,urutan:string}|null
     */
    private function detectColumns(Worksheet $sheet): ?array
    {
        $highestRow = min($sheet->getHighestDataRow(), 10);
        $highestColumn = $sheet->getHighestDataColumn();

        for ($rowNum = 1; $rowNum <= $highestRow; $rowNum++) {
            $skuCol = null;
            $urutanCol = null;

            foreach ($this->columnRange('A', $highestColumn) as $col) {
                $value = strtoupper(trim((string) $sheet->getCell("{$col}{$rowNum}")->getValue()));

                if ($value === '') {
                    continue;
                }

                if ($value === 'SKU') {
                    $skuCol = $col;
                } elseif (str_contains($value, 'URUTAN')) {
                    $urutanCol = $col;
                }
            }

            if ($skuCol && $urutanCol) {
                return ['header' => $rowNum, 'sku' => $skuCol, 'urutan' => $urutanCol];
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function columnRange(string $from, string $to): array
    {
        $columns = [];
        $current = $from;

        while (true) {
            $columns[] = $current;

            if ($current === $to) {
                break;
            }

            $current = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(
                \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($current) + 1
            );

            if (count($columns) > 100) {
                break;
            }
        }

        return $columns;
    }
}
