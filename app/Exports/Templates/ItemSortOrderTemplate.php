<?php

namespace App\Exports\Templates;

use App\Exports\Concerns\WithSifobiExcelStyles;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Template Excel Urutan Item: baris = SEMUA item departemen terpilih,
 * sudah terkelompok & terurut sesuai Kategori/Sub Kategori "Sesuai Form"
 * (Item::formSortKey()) + urutan item yang SUDAH ada (kalau ada). Kolom
 * "Urutan" dikosongkan untuk item yang belum di-mapping -- tim tinggal
 * mengisi angka sesuai posisi di form fisik untuk item yang urutannya mau
 * diatur, baris lain boleh dibiarkan kosong (lihat ItemSortOrderImport).
 * Departemen TIDAK ditulis di dalam file -- diisi ulang eksplisit lewat
 * form upload, sama seperti OpnameMonthlyImportTemplate.
 */
class ItemSortOrderTemplate implements WithMultipleSheets
{
    /**
     * @param  Collection<int, \App\Modules\Inventory\Models\Item>  $items
     */
    public function __construct(
        private readonly Collection $items,
        private readonly int $departmentId,
    ) {
    }

    public function sheets(): array
    {
        return [
            new ItemSortOrderTemplateSheet($this->items, $this->departmentId),
            new ItemSortOrderInstructionSheet(),
        ];
    }
}

class ItemSortOrderTemplateSheet implements FromArray, ShouldAutoSize, WithEvents, WithStyles, WithTitle
{
    use WithSifobiExcelStyles;

    /**
     * @param  Collection<int, \App\Modules\Inventory\Models\Item>  $items
     */
    public function __construct(
        private readonly Collection $items,
        private readonly int $departmentId,
    ) {
    }

    public function array(): array
    {
        $sorted = $this->items->sortBy(fn ($item) => $item->formSortKey($this->departmentId));

        $rows = [
            ['SKU', 'Item', 'Kategori', 'Sub Kategori', 'Urutan'],
        ];

        foreach ($sorted as $item) {
            $leaf = $item->categoryForDepartment($this->departmentId);
            $parent = $leaf?->parent ?? $leaf;

            $rows[] = [
                $item->canonical_sku,
                $item->name,
                $parent?->name ?: '-',
                ($leaf && $leaf->parent_id) ? $leaf->name : '-',
                $item->sortOrderForDepartment($this->departmentId) ?: '',
            ];
        }

        return $rows;
    }

    public function styles(Worksheet $sheet): array
    {
        return [];
    }

    public function title(): string
    {
        return 'URUTAN_ITEM';
    }
}

class ItemSortOrderInstructionSheet implements FromArray, ShouldAutoSize, WithStyles, WithTitle
{
    public function array(): array
    {
        return [
            ['Kolom', 'Keterangan', 'Wajib?'],
            ['SKU', 'Kunci pencocokan item saat upload -- JANGAN diubah/dihapus', 'Ya'],
            ['Item', 'Nama item, cuma informasi -- tidak perlu diubah', 'Tidak'],
            ['Kategori', 'Kategori utama item di departemen ini, cuma informasi', 'Tidak'],
            ['Sub Kategori', 'Sub kategori item di departemen ini, cuma informasi', 'Tidak'],
            ['Urutan', 'Nomor urut item di dalam sub kategorinya, SESUAI posisi di form fisik. Boleh dikosongkan untuk item yang belum mau diatur urutannya', 'Tidak'],
            [],
            ['CATATAN PENTING:'],
            ['1. Baris sudah terurut sesuai Kategori & Sub Kategori "Sesuai Form" (dari Mapping Departemen & Kategori Opname) -- tinggal isi kolom Urutan sesuai posisi di form fisik tim untuk tiap sub kategori (mulai dari 1 lagi di tiap sub kategori baru).'],
            ['2. Baris dengan Urutan dikosongkan TIDAK akan mengubah urutan yang sudah ada sebelumnya (kalau ada) -- aman upload file yang cuma diisi sebagian.'],
            ['3. Item yang belum diisi Urutan akan tetap tampil, tapi di urutan PALING BELAKANG sub kategorinya (alfabetis di antara sesama yang belum diurutkan).'],
            ['4. Upload ulang file yang sama atau file yang diperbarui aman dilakukan (upsert berdasarkan SKU) -- tidak membuat data dobel.'],
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1B4332']],
            ],
        ];
    }

    public function title(): string
    {
        return 'PETUNJUK';
    }
}
