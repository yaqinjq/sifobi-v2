<?php

namespace App\Imports;

use App\Modules\Core\Models\Department;
use App\Modules\Inventory\Models\ItemCategory;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use RuntimeException;
use Throwable;

/**
 * Import mapping Departemen -> Kategori -> Sub Kategori (dengan urutan) dari
 * Excel. Sengaja dibaca lewat sel mentah (bukan Laravel-Excel
 * ToCollection/WithHeadingRow) mengikuti pola WiproCatalogImport --
 * mengikuti bentuk ASLI file "Mapping Daily Opname.xlsx" dari pimpinan
 * yang punya baris judul kosong di atas, header BUKAN di baris pertama,
 * dan kolom Departemen/Kategori cuma diisi sekali per kelompok (baris
 * berikutnya kosong, mengikuti kebiasaan sel gabungan/visual grouping di
 * Excel) -- bukan format flat 1-header-per-kolom yang lurus.
 *
 * Tujuan fitur ini: supaya filter Kategori di halaman Opname cuma
 * menampilkan kategori yang relevan untuk departemen sesi tsb (bukan semua
 * kategori master), dan item di dalam kategori tampil sesuai urutan form,
 * bukan abjad -- lihat Department::itemCategories() dan
 * ItemCategory::parent()/children().
 *
 * Upsert idempoten berdasarkan NAMA kategori (bukan nama+parent) --
 * item_categories punya unique constraint (tenant_id, name) sejak migration
 * dedup_item_categories_add_unique_name, jadi nama kategori/sub-kategori
 * memang harus unik per tenant apa pun parent-nya. Kalau kategori dengan
 * nama yang sama sudah ada (mis. dari Master Data lama), baris tsb dipakai
 * ulang (parent & urutan disesuaikan), TIDAK dibuatkan duplikat baru --
 * inilah yang tadinya bikin gagal "Duplicate entry" saat upload file asli.
 */
class DepartmentCategoryMappingImport
{
    private int $processed = 0;

    /**
     * @var list<array{row:int,message:string}>
     */
    private array $rowErrors = [];

    public function __construct(
        private readonly int $tenantId,
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
                'message' => 'Format file tidak dikenali -- pastikan ada kolom/header "Departemen", "Kategori", dan "Sub Kategori".',
            ];

            return;
        }

        ['header' => $headerRow, 'departemen' => $departemenCol, 'kategori' => $kategoriCol, 'urutan' => $urutanCol, 'subKategori' => $subKategoriCol] = $columns;

        $lastDepartemen = '';
        $lastKategori = '';
        $highestRow = $sheet->getHighestDataRow();

        for ($rowNum = $headerRow + 1; $rowNum <= $highestRow; $rowNum++) {
            $departemen = trim((string) $sheet->getCell("{$departemenCol}{$rowNum}")->getValue());
            $kategori = trim((string) $sheet->getCell("{$kategoriCol}{$rowNum}")->getValue());
            $urutan = trim((string) $sheet->getCell("{$urutanCol}{$rowNum}")->getValue());
            $subKategori = trim((string) $sheet->getCell("{$subKategoriCol}{$rowNum}")->getValue());

            if ($departemen === '' && $kategori === '' && $subKategori === '') {
                continue; // baris kosong pemisah antar kelompok -- bukan error
            }

            // Baris berikutnya dalam satu kelompok cuma mengisi sub-kategori,
            // Departemen & Kategori dianggap sama dengan baris terakhir yang
            // benar-benar mengisinya (gaya sel gabungan/visual grouping).
            $departemen = $departemen !== '' ? $departemen : $lastDepartemen;
            $kategori = $kategori !== '' ? $kategori : $lastKategori;

            if ($departemen !== '') {
                $lastDepartemen = $departemen;
            }
            if ($kategori !== '') {
                $lastKategori = $kategori;
            }

            try {
                if ($departemen === '') {
                    throw new RuntimeException('Departemen tidak ditemukan (baris ini maupun baris sebelumnya di kelompok yang sama).');
                }
                if ($kategori === '') {
                    throw new RuntimeException('Kategori tidak ditemukan (baris ini maupun baris sebelumnya di kelompok yang sama).');
                }

                $this->importRow($departemen, $kategori, $subKategori, $urutan);
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

    private function importRow(string $departmentLabel, string $categoryName, string $subCategoryName, string $urutan): void
    {
        $department = Department::query()
            ->where('tenant_id', $this->tenantId)
            ->where(fn ($q) => $q->where('name', $departmentLabel)->orWhere('code', $departmentLabel))
            ->first();

        if (! $department) {
            throw new RuntimeException("Departemen '{$departmentLabel}' tidak ditemukan.");
        }

        // Cari berdasarkan NAMA saja (bukan nama+parent) -- item_categories
        // unik per (tenant_id, name), jadi kategori yang sudah ada (dari
        // import sebelumnya ATAU dari Master Data lama) dipakai ulang, tidak
        // dibuatkan duplikat yang bakal ditolak database.
        $category = ItemCategory::query()
            ->where('tenant_id', $this->tenantId)
            ->where('name', $categoryName)
            ->first();

        if ($category) {
            if ($category->parent_id !== null) {
                $category->update(['parent_id' => null]);
            }
        } else {
            $category = ItemCategory::query()->create([
                'tenant_id' => $this->tenantId,
                'parent_id' => null,
                'code' => $this->uniqueCode($categoryName),
                'name' => $categoryName,
                'status' => 'ACTIVE',
                'is_active' => true,
                'sort_order' => 0,
            ]);
        }

        $department->itemCategories()->syncWithoutDetaching([$category->id]);

        if ($subCategoryName === '') {
            return;
        }

        $sortOrder = (int) ($urutan ?: 0);

        $sub = ItemCategory::query()
            ->where('tenant_id', $this->tenantId)
            ->where('name', $subCategoryName)
            ->first();

        if ($sub) {
            $sub->update([
                'parent_id' => $category->id,
                'sort_order' => $sortOrder,
            ]);

            return;
        }

        ItemCategory::query()->create([
            'tenant_id' => $this->tenantId,
            'parent_id' => $category->id,
            'code' => $this->uniqueCode($categoryName.'-'.$subCategoryName),
            'name' => $subCategoryName,
            'status' => 'ACTIVE',
            'is_active' => true,
            'sort_order' => $sortOrder,
        ]);
    }

    /**
     * Cari baris header (mengandung sel "Departemen") dan kolom-kolom yang
     * relevan berdasarkan teks header-nya -- bukan posisi tetap -- supaya
     * tetap jalan baik untuk file resmi pimpinan (header di baris ke-3,
     * ada kolom Gudang yang diabaikan) maupun format sederhana
     * departemen/kategori/urutan/sub_kategori di baris pertama.
     *
     * @return array{header:int,departemen:string,kategori:string,urutan:string,subKategori:string}|null
     */
    private function detectColumns(Worksheet $sheet): ?array
    {
        $highestRow = min($sheet->getHighestDataRow(), 10);
        $highestColumn = $sheet->getHighestDataColumn();

        for ($rowNum = 1; $rowNum <= $highestRow; $rowNum++) {
            $departemenCol = null;
            $kategoriCol = null;
            $subKategoriCol = null;
            $urutanCol = null;

            foreach ($this->columnRange('A', $highestColumn) as $col) {
                $value = strtoupper(trim((string) $sheet->getCell("{$col}{$rowNum}")->getValue()));

                if ($value === '') {
                    continue;
                }

                if (str_contains($value, 'SUB') && str_contains($value, 'KATEGORI')) {
                    $subKategoriCol = $col;
                } elseif (str_contains($value, 'KATEGORI')) {
                    $kategoriCol = $col;
                } elseif (str_contains($value, 'DEPARTEMEN') || str_contains($value, 'DEPARTMENT')) {
                    $departemenCol = $col;
                } elseif (str_contains($value, 'URUTAN') || str_contains($value, 'NO')) {
                    $urutanCol = $col;
                }
            }

            if ($departemenCol && $kategoriCol && $subKategoriCol) {
                if (! $urutanCol) {
                    // File resmi tidak memberi label pada kolom nomor urut --
                    // posisinya selalu tepat di kiri kolom Sub Kategori.
                    $urutanCol = $this->columnBefore($subKategoriCol);
                }

                return [
                    'header' => $rowNum,
                    'departemen' => $departemenCol,
                    'kategori' => $kategoriCol,
                    'urutan' => $urutanCol,
                    'subKategori' => $subKategoriCol,
                ];
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

    private function columnBefore(string $column): string
    {
        $index = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($column);

        return \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(max(1, $index - 1));
    }

    private function uniqueCode(string $label): string
    {
        $base = Str::slug($label, '_');
        $code = mb_substr($base, 0, 50);
        $suffix = 1;

        while (ItemCategory::query()->where('tenant_id', $this->tenantId)->where('code', $code)->exists()) {
            $code = mb_substr($base, 0, 46).'_'.$suffix;
            $suffix++;
        }

        return $code;
    }
}
