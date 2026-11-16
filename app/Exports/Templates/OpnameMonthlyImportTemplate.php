<?php

namespace App\Exports\Templates;

use App\Support\Excel\CategoryDividerWriter;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Template Excel Opname Bulanan: baris = item, kolom = 4 sub-kolom
 * (IN|OUT|SPOIL|BAL) per tanggal di bulan terpilih -- persis form kertas
 * harian yang dipakai outlet (lihat OpnameMonthlyImport untuk makna tiap
 * sub-kolom). Konteks outlet/departemen/bulan TIDAK ditulis di dalam file
 * -- diisi ulang eksplisit lewat form upload.
 *
 * HANYA BALANCE yang menyentuh ledger stok (lewat OpnameService::updateItem()
 * di processDate()) -- IN/OUT/SPOIL murni arsip laporan outlet
 * (opname_items.reported_in_qty/out_qty/spoil_qty), tidak pernah membentuk
 * mutasi stok apa pun (lihat Context di dokumen plan untuk alasannya: makna
 * "OUT" beda-beda tergantung baris lokasi, akan ambigu kalau dipaksa jadi 1
 * jenis mutasi).
 *
 * Urutan baris memakai Item::formSortKey() -- SAMA PERSIS dengan urutan
 * "Sesuai Form" di tampilan Opname harian. Kategori sebagai baris pemisah.
 */
class OpnameMonthlyImportTemplate implements WithMultipleSheets
{
    /**
     * @param  Collection<int, \App\Modules\Inventory\Models\Item>  $items
     * @param  list<string>  $dates  Y-m-d
     * @param  array<string, array{in: ?string, out: ?string, spoil: ?string, balance: ?string}>  $prefill  key "{item_id}|{date}"
     */
    public function __construct(
        private readonly Collection $items,
        private readonly array $dates,
        private readonly ?int $departmentId,
        private readonly array $prefill = [],
    ) {
    }

    public function sheets(): array
    {
        return [
            new OpnameMonthlyTemplateSheet($this->items, $this->dates, $this->departmentId, $this->prefill),
            new OpnameMonthlyInstructionSheet(),
        ];
    }
}

class OpnameMonthlyTemplateSheet implements FromArray, ShouldAutoSize, WithEvents, WithStyles, WithTitle
{
    private const SUB_LABELS = ['IN', 'OUT', 'SPOIL', 'BAL'];

    /** @var list<int> baris (1-indexed) tempat kategori ditulis -- diisi array(), dibaca registerEvents() */
    private array $categoryRows = [];

    /**
     * @param  Collection<int, \App\Modules\Inventory\Models\Item>  $items
     * @param  list<string>  $dates
     * @param  array<string, array{in: ?string, out: ?string, spoil: ?string, balance: ?string}>  $prefill
     */
    public function __construct(
        private readonly Collection $items,
        private readonly array $dates,
        private readonly ?int $departmentId,
        private readonly array $prefill = [],
    ) {
    }

    public function array(): array
    {
        $sorted = $this->items->sortBy(fn ($item) => $item->formSortKey($this->departmentId));
        $grouped = $sorted->groupBy(fn ($item) => $item->categoryForDepartment($this->departmentId)?->name ?: 'Lainnya');

        // Row 1: label tanggal (ditulis sama persis ke 4 sub-kolomnya --
        // akan di-merge horizontal di registerEvents() -- supaya importer
        // tetap bisa baca tanggalnya biarpun visual sudah merged).
        // Row 2: label sub-kolom IN/OUT/SPOIL/BAL, diulang per tanggal.
        $headerRow1 = [];
        $headerRow2 = [];

        foreach (['Item', 'SKU', 'Satuan Hitung'] as $label) {
            $headerRow1[] = $label;
            $headerRow2[] = $label;
        }

        foreach ($this->dates as $date) {
            foreach (self::SUB_LABELS as $subLabel) {
                $headerRow1[] = $date;
                $headerRow2[] = $subLabel;
            }
        }

        $rows = [$headerRow1, $headerRow2];

        foreach ($grouped as $category => $itemsInCategory) {
            $this->categoryRows[] = count($rows) + 1;
            $rows[] = array_merge([$category], array_fill(0, 2 + count($this->dates) * 4, ''));

            foreach ($itemsInCategory as $item) {
                $row = [
                    $item->name,
                    $item->canonical_sku,
                    $item->inventoryUnit?->abbreviation ?: $item->baseUnit?->abbreviation ?: '-',
                ];

                foreach ($this->dates as $date) {
                    $data = $this->prefill["{$item->id}|{$date}"] ?? null;
                    $row[] = $data['in'] ?? '';
                    $row[] = $data['out'] ?? '';
                    $row[] = $data['spoil'] ?? '';
                    $row[] = $data['balance'] ?? '';
                }

                $rows[] = $row;
            }
        }

        return $rows;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();
                $lastCol = Coordinate::stringFromColumnIndex(3 + count($this->dates) * 4);

                $sheet->freezePane('A3');

                // CATATAN: kolom Item/SKU/Satuan Hitung SENGAJA TIDAK
                // di-mergeCells() vertikal -- PhpSpreadsheet::mergeCells()
                // MENGHAPUS value cell non-top-left (bukan cuma efek visual),
                // importer baca header dari BARIS 1 (bukan WithHeadingRow,
                // lihat OpnameMonthlyImport::parseGrid()) jadi aman asal
                // row 1 tidak kosong -- tapi dibiarkan 2 baris sama persis
                // (ditulis di array()) supaya tetap konsisten visual dengan
                // header tanggal di sebelahnya yang memang 2 baris.

                // Merge horizontal 4 sub-kolom per tanggal di baris 1.
                $col = 4;
                foreach ($this->dates as $date) {
                    $fromCol = Coordinate::stringFromColumnIndex($col);
                    $toCol = Coordinate::stringFromColumnIndex($col + 3);
                    $sheet->mergeCells("{$fromCol}1:{$toCol}1");
                    $col += 4;
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
        return 'OPNAME_BULANAN';
    }
}

class OpnameMonthlyInstructionSheet implements FromArray, ShouldAutoSize, WithStyles, WithTitle
{
    public function array(): array
    {
        return [
            ['Kolom', 'Keterangan', 'Wajib?'],
            ['(baris abu-abu)', 'Nama kategori sebagai baris pemisah -- cuma informasi, tidak perlu diubah', 'Tidak'],
            ['Item', 'Nama item, cuma informasi -- tidak perlu diubah', 'Tidak'],
            ['SKU', 'SKU item, cuma informasi -- tidak perlu diubah', 'Tidak'],
            ['Satuan Hitung', 'Satuan yang dipakai untuk SEMUA sel tanggal di baris itu -- cuma informasi, tidak perlu diubah', 'Tidak'],
            ['<tanggal> - IN', 'Jumlah barang masuk yang dilaporkan outlet pada tanggal itu -- CUMA ARSIP, tidak mempengaruhi stok sistem', 'Tidak'],
            ['<tanggal> - OUT', 'Jumlah barang keluar yang dilaporkan outlet -- CUMA ARSIP, tidak mempengaruhi stok sistem', 'Tidak'],
            ['<tanggal> - SPOIL', 'Jumlah rusak/kadaluarsa yang dilaporkan outlet -- CUMA ARSIP, tidak mempengaruhi stok sistem', 'Tidak'],
            ['<tanggal> - BAL', 'Hasil hitung fisik (saldo akhir) untuk tanggal itu, dalam satuan "Satuan Hitung" -- SATU-SATUNYA kolom yang menentukan stok sistem. Kosongkan kalau tidak dihitung tanggal itu (BUKAN isi 0)', 'Isi sesuai kebutuhan'],
            [],
            ['CATATAN PENTING:'],
            ['1. Sel BAL kosong = tidak dihitung tanggal itu, BUKAN dianggap nol. Sel IN/OUT/SPOIL kosong = tidak dilaporkan, masing-masing independen.'],
            ['2. Setelah diupload, akan ada halaman preview per minggu untuk dicek dulu sebelum benar-benar disimpan.'],
            ['3. Begitu 1 tanggal dicentang & diproses, sesi opname tanggal itu LANGSUNG disetujui (approved) dan saldo stok langsung disesuaikan berdasarkan BAL -- tidak ada langkah approve manual terpisah sesudahnya.'],
            ['4. Proses penyimpanan berjalan di belakang layar (butuh waktu, tidak langsung selesai saat diklik) -- halaman preview akan menunjukkan progressnya.'],
            ['5. Tanggal yang sudah berstatus Processed: kolom BAL TIDAK bisa diubah lagi lewat import ini (stok sudah final), TAPI kolom IN/OUT/SPOIL tetap bisa dikoreksi/diupdate kapan pun lewat upload ulang -- download template baru dulu supaya angka yang sudah ada ikut terisi otomatis.'],
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
