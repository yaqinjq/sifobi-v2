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
 * Template Excel Opname Bulanan: baris = item, kolom = tiap tanggal di
 * bulan terpilih. Konteks outlet/departemen/bulan TIDAK ditulis di dalam
 * file -- diisi ulang eksplisit lewat form upload supaya tidak
 * mengandalkan parsing cell header yang rapuh.
 *
 * Urutan baris memakai Item::formSortKey() -- SAMA PERSIS dengan urutan
 * "Sesuai Form" di tampilan Opname harian (kategori utama -> sub kategori
 * -> nama item), sumbernya data "Mapping Departemen & Kategori Opname"
 * yang sudah diupload lewat Settings. Departemen yang belum punya mapping
 * kategori akan jatuh ke urutan alfabetis biasa (parent/child sort_order
 * default 0).
 */
class OpnameMonthlyImportTemplate implements WithMultipleSheets
{
    /**
     * @param  Collection<int, \App\Modules\Inventory\Models\Item>  $items
     * @param  list<string>  $dates  Y-m-d
     */
    public function __construct(
        private readonly Collection $items,
        private readonly array $dates,
        private readonly ?int $departmentId,
    ) {
    }

    public function sheets(): array
    {
        return [
            new OpnameMonthlyTemplateSheet($this->items, $this->dates, $this->departmentId),
            new OpnameMonthlyInstructionSheet(),
        ];
    }
}

class OpnameMonthlyTemplateSheet implements FromArray, ShouldAutoSize, WithEvents, WithStyles, WithTitle
{
    use WithSifobiExcelStyles;

    /**
     * @param  Collection<int, \App\Modules\Inventory\Models\Item>  $items
     * @param  list<string>  $dates
     */
    public function __construct(
        private readonly Collection $items,
        private readonly array $dates,
        private readonly ?int $departmentId,
    ) {
    }

    public function array(): array
    {
        $sorted = $this->items->sortBy(fn ($item) => $item->formSortKey($this->departmentId));

        $rows = [
            array_merge(['Kategori', 'Item', 'SKU', 'Satuan Hitung'], $this->dates),
        ];

        foreach ($sorted as $item) {
            $rows[] = array_merge([
                $item->categoryForDepartment($this->departmentId)?->name ?: 'Lainnya',
                $item->name,
                $item->canonical_sku,
                $item->inventoryUnit?->abbreviation ?: $item->baseUnit?->abbreviation ?: '-',
            ], array_fill(0, count($this->dates), ''));
        }

        return $rows;
    }

    public function styles(Worksheet $sheet): array
    {
        return [];
    }

    public function title(): string
    {
        return 'OPNAME_BULANAN';
    }
}

class OpnameMonthlyInstructionSheet implements FromArray, ShouldAutoSize, WithStyles, WithTitle
{
    public function array(): array
    {
        return [
            ['Kolom', 'Keterangan', 'Wajib?'],
            ['Kategori', 'Kategori item, cuma informasi -- tidak perlu diubah', 'Tidak'],
            ['Item', 'Nama item, cuma informasi -- tidak perlu diubah', 'Tidak'],
            ['SKU', 'SKU item, cuma informasi -- tidak perlu diubah', 'Tidak'],
            ['Satuan Hitung', 'Satuan yang dipakai untuk SEMUA sel tanggal di baris itu -- cuma informasi, tidak perlu diubah', 'Tidak'],
            ['<tanggal>', 'Isi hasil hitung fisik untuk item itu pada tanggal tersebut, dalam satuan di kolom "Satuan Hitung". Kosongkan kalau tidak dihitung tanggal itu (BUKAN isi 0)', 'Isi sesuai kebutuhan'],
            [],
            ['CATATAN PENTING:'],
            ['1. Sel kosong = tidak dihitung tanggal itu, BUKAN dianggap nol.'],
            ['2. Setelah diupload, akan ada halaman preview per minggu untuk dicek dulu sebelum benar-benar disimpan.'],
            ['3. Begitu 1 tanggal dicentang & diproses, sesi opname tanggal itu LANGSUNG disetujui (approved) dan saldo stok langsung disesuaikan -- tidak ada langkah approve manual terpisah sesudahnya.'],
            ['4. Proses penyimpanan berjalan di belakang layar (butuh waktu, tidak langsung selesai saat diklik) -- halaman preview akan menunjukkan progressnya.'],
            ['5. Tanggal yang sudah punya sesi opname berstatus Submitted/Processed TIDAK bisa diubah lewat import ini -- akan dilewati otomatis.'],
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
