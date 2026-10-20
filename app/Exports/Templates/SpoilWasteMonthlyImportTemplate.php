<?php

namespace App\Exports\Templates;

use App\Modules\Operations\Models\SpoilWaste;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Template Excel Spoil & Waste Bulanan: baris = item (urut kategori lalu
 * nama), kolom = tiap tanggal di bulan terpilih. 1 "Kategori Alasan
 * (default)" per baris item untuk seluruh bulan -- kalau 1 hari tertentu
 * alasannya beda dari biasanya, dicatat manual lewat form "Catat Spoil"
 * (sudah bisa multi-item) atau lewat import historis flat yang sudah ada.
 */
class SpoilWasteMonthlyImportTemplate implements WithMultipleSheets
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
            new SpoilWasteMonthlyTemplateSheet($this->items, $this->dates, $this->departmentId),
            new SpoilWasteMonthlyInstructionSheet(),
        ];
    }
}

class SpoilWasteMonthlyTemplateSheet implements FromArray, ShouldAutoSize, WithStyles, WithTitle
{
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
        $grouped = $this->items
            ->groupBy(fn ($item) => $item->categoryForDepartment($this->departmentId)?->name ?: 'Lainnya')
            ->sortKeys();

        $rows = [
            array_merge(['Kategori', 'Item', 'SKU', 'Satuan Hitung', 'Kategori Alasan (default)'], $this->dates),
        ];

        foreach ($grouped as $category => $items) {
            foreach ($items->sortBy('name') as $item) {
                $rows[] = array_merge([
                    $category,
                    $item->name,
                    $item->canonical_sku,
                    $item->inventoryUnit?->abbreviation ?: $item->baseUnit?->abbreviation ?: '-',
                    '',
                ], array_fill(0, count($this->dates), ''));
            }
        }

        return $rows;
    }

    public function styles(Worksheet $sheet): array
    {
        $highestColumn = $sheet->getHighestColumn();

        return [
            "A1:{$highestColumn}1" => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1B4332']],
            ],
        ];
    }

    public function title(): string
    {
        return 'SPOIL_BULANAN';
    }
}

class SpoilWasteMonthlyInstructionSheet implements FromArray, ShouldAutoSize, WithStyles, WithTitle
{
    public function array(): array
    {
        $reasons = [
            SpoilWaste::REASON_EXPIRED => 'Kadaluarsa',
            SpoilWaste::REASON_RUSAK => 'Rusak/Cacat',
            SpoilWaste::REASON_KESALAHAN_PRODUKSI => 'Kesalahan Produksi',
            SpoilWaste::REASON_TUMPAH => 'Tumpah',
            SpoilWaste::REASON_QUALITY_REJECT => 'Reject Kualitas',
            SpoilWaste::REASON_LAINNYA => 'Lainnya',
        ];

        return [
            ['Kolom', 'Keterangan', 'Wajib?'],
            ['Kategori', 'Kategori item, cuma informasi -- tidak perlu diubah', 'Tidak'],
            ['Item', 'Nama item, cuma informasi -- tidak perlu diubah', 'Tidak'],
            ['SKU', 'SKU item, cuma informasi -- tidak perlu diubah', 'Tidak'],
            ['Satuan Hitung', 'Satuan yang dipakai untuk SEMUA sel tanggal di baris itu', 'Tidak'],
            ['Kategori Alasan (default)', 'Salah satu: '.implode(', ', $reasons).' -- dipakai untuk SEMUA tanggal terisi di baris itu', 'Ya, kalau ada sel tanggal yang diisi'],
            ['<tanggal>', 'Isi jumlah yang spoil/waste untuk item itu pada tanggal tersebut, dalam satuan di kolom "Satuan Hitung". Kosongkan kalau tidak ada kejadian tanggal itu', 'Isi sesuai kebutuhan'],
            [],
            ['CATATAN PENTING:'],
            ['1. Sel kosong = tidak ada kejadian tanggal itu.'],
            ['2. Kalau 1 hari alasannya beda dari "Kategori Alasan (default)" di baris itu, catat hari itu manual lewat form Catat Spoil (bisa multi-item), bukan lewat grid bulanan ini.'],
            ['3. Setelah diupload, akan ada halaman preview per minggu untuk dicek dulu sebelum benar-benar disimpan.'],
            ['4. Begitu 1 sel dicentang & diproses, laporan spoil tanggal itu LANGSUNG disetujui (approved) dan stok langsung terpotong -- tidak ada langkah approve manual terpisah sesudahnya.'],
            ['5. Proses penyimpanan berjalan di belakang layar (butuh waktu, tidak langsung selesai saat diklik) -- halaman preview akan menunjukkan progressnya.'],
            ['6. Kalau sel itu cocok dengan laporan yang SUDAH final/approved sebelumnya, sistem akan membatalkan (void) catatan lama dan mencatat ulang dengan nilai baru -- butuh centang konfirmasi tambahan di preview.'],
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
