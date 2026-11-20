<?php

namespace App\Exports\Templates;

use App\Modules\Operations\Models\SpoilWaste;
use App\Support\Excel\CategoryDividerWriter;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Template Excel Spoil & Waste Bulanan: baris = item (urut kategori lalu
 * nama), tiap tanggal di bulan terpilih punya 3 sub-kolom QTY | ALASAN |
 * KET -- persis form kertas yang mencatat keterangan per kejadian (Gosong,
 * Cair, Penyet, ...). ALASAN = dropdown 6 kategori; kosong = pakai
 * "Kategori Alasan (default)" di kolom D. KET = teks bebas, disimpan sbg
 * keterangan detail spoil.
 *
 * Header 2 baris: baris 1 = label tanggal (ditulis ke 3 sub-kolomnya lalu
 * di-merge horizontal -- importer baca dari baris 1), baris 2 = sub-label.
 * SpoilWasteMonthlyImport tetap menerima format lama (1 kolom per tanggal).
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

class SpoilWasteMonthlyTemplateSheet implements FromArray, ShouldAutoSize, WithEvents, WithStyles, WithTitle
{
    public const SUB_LABELS = ['QTY', 'ALASAN', 'KET'];

    private const FIXED_COLUMNS = 4;

    /** @var list<int> baris (1-indexed) tempat kategori ditulis -- diisi array(), dibaca registerEvents() */
    private array $categoryRows = [];

    private int $lastRow = 2;

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

        $fixed = ['Item', 'SKU', 'Satuan Hitung', 'Kategori Alasan (default)'];
        $headerRow1 = $fixed;
        $headerRow2 = $fixed;

        foreach ($this->dates as $date) {
            foreach (self::SUB_LABELS as $subLabel) {
                $headerRow1[] = $date;
                $headerRow2[] = $subLabel;
            }
        }

        $rows = [$headerRow1, $headerRow2];
        $width = self::FIXED_COLUMNS + count($this->dates) * count(self::SUB_LABELS);

        foreach ($grouped as $category => $items) {
            $this->categoryRows[] = count($rows) + 1;
            $rows[] = array_merge([$category], array_fill(0, $width - 1, ''));

            foreach ($items->sortBy('name') as $item) {
                $rows[] = array_merge([
                    $item->name,
                    $item->canonical_sku,
                    $item->inventoryUnit?->abbreviation ?: $item->baseUnit?->abbreviation ?: '-',
                    '',
                ], array_fill(0, $width - self::FIXED_COLUMNS, ''));
            }
        }

        $this->lastRow = count($rows);

        return $rows;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();
                $perDate = count(self::SUB_LABELS);
                $lastCol = Coordinate::stringFromColumnIndex(self::FIXED_COLUMNS + count($this->dates) * $perDate);

                $sheet->freezePane('E3');

                // Item/SKU/Satuan/Default SENGAJA tidak di-merge vertikal --
                // mergeCells() menghapus value sel non-top-left (lihat
                // catatan di OpnameMonthlyImportTemplate).
                $reasonList = '"'.implode(',', SpoilWasteMonthlyInstructionSheet::reasonLabels()).'"';
                $col = self::FIXED_COLUMNS + 1;

                foreach ($this->dates as $date) {
                    $fromCol = Coordinate::stringFromColumnIndex($col);
                    $toCol = Coordinate::stringFromColumnIndex($col + $perDate - 1);
                    $sheet->mergeCells("{$fromCol}1:{$toCol}1");

                    // 1 dropdown per kolom ALASAN (range), bukan per sel --
                    // writer memakai getSqref(), file tetap kecil.
                    if ($this->lastRow >= 3) {
                        $reasonCol = Coordinate::stringFromColumnIndex($col + 1);
                        $validation = new DataValidation();
                        $validation->setType(DataValidation::TYPE_LIST)
                            ->setAllowBlank(true)
                            ->setShowDropDown(true)
                            ->setShowErrorMessage(true)
                            ->setErrorTitle('Alasan tidak dikenali')
                            ->setError('Pilih salah satu kategori alasan dari daftar.')
                            ->setFormula1($reasonList)
                            ->setSqref("{$reasonCol}3:{$reasonCol}{$this->lastRow}");
                        $sheet->setDataValidation("{$reasonCol}3", $validation);
                    }

                    $col += $perDate;
                }

                $sheet->getStyle("A1:{$lastCol}2")->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1B4332']],
                    'alignment' => ['horizontal' => 'center'],
                ]);

                foreach ($this->categoryRows as $row) {
                    $category = (string) $sheet->getCell("A{$row}")->getValue();
                    $sheet->setCellValue("A{$row}", '');
                    CategoryDividerWriter::write($sheet, $row, 'A', $lastCol, $category);
                }
            },
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [];
    }

    public function title(): string
    {
        return 'SPOIL_BULANAN';
    }
}

class SpoilWasteMonthlyInstructionSheet implements FromArray, ShouldAutoSize, WithStyles, WithTitle
{
    /**
     * @return array<string, string>
     */
    public static function reasonLabels(): array
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

    public function array(): array
    {
        $reasons = self::reasonLabels();

        return [
            ['Kolom', 'Keterangan', 'Wajib?'],
            ['(baris abu-abu)', 'Nama kategori sebagai baris pemisah -- cuma informasi, tidak perlu diubah', 'Tidak'],
            ['Item', 'Nama item, cuma informasi -- tidak perlu diubah', 'Tidak'],
            ['SKU', 'SKU item, cuma informasi -- tidak perlu diubah', 'Tidak'],
            ['Satuan Hitung', 'Satuan yang dipakai untuk SEMUA kolom QTY di baris itu', 'Tidak'],
            ['Kategori Alasan (default)', 'Salah satu: '.implode(', ', $reasons).' -- dipakai untuk tanggal yang kolom ALASAN-nya dikosongkan', 'Ya, kalau ada ALASAN tanggal yang dikosongkan'],
            ['<tanggal> - QTY', 'Jumlah spoil/waste item itu pada tanggal tersebut, dalam satuan "Satuan Hitung". Kosongkan kalau tidak ada kejadian', 'Isi sesuai kebutuhan'],
            ['<tanggal> - ALASAN', 'Pilih dari dropdown: '.implode(', ', $reasons).'. Kosong = pakai "Kategori Alasan (default)" di kolom D', 'Tidak'],
            ['<tanggal> - KET', 'Keterangan bebas seperti di form kertas (mis. Gosong, Cair, Penyet, Salah buat) -- disimpan sebagai keterangan detail spoil', 'Tidak'],
            [],
            ['CATATAN PENTING:'],
            ['1. QTY kosong = tidak ada kejadian tanggal itu (ALASAN/KET di tanggal itu diabaikan).'],
            ['2. Alasan bisa beda tiap tanggal -- isi kolom ALASAN tanggal itu. Contoh: Egg Tart Mini tgl 1 Rusak/Cacat, tgl 3 Kadaluarsa.'],
            ['3. Item yang sama spoil 2x di HARI yang sama: jumlahkan QTY-nya, tulis keduanya di KET (mis. "1 gosong, 1 penyet").'],
            ['4. Setelah diupload, akan ada halaman preview per minggu untuk dicek dulu sebelum benar-benar disimpan.'],
            ['5. Begitu 1 sel dicentang & diproses, laporan spoil tanggal itu LANGSUNG disetujui (approved) -- tidak ada langkah approve manual terpisah sesudahnya.'],
            ['6. Proses penyimpanan berjalan di belakang layar (butuh waktu, tidak langsung selesai saat diklik) -- halaman preview akan menunjukkan progressnya.'],
            ['7. Kalau sel itu cocok dengan laporan yang SUDAH final/approved sebelumnya, sistem akan mencatat ulang dengan nilai baru -- butuh centang konfirmasi tambahan di preview.'],
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
