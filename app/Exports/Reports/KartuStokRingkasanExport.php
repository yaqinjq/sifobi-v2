<?php

namespace App\Exports\Reports;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Grid harian (IN/OUT/SPOIL/BAL per tanggal) -- isinya sama persis dengan
 * tampilan tabel di laporan.kartu-stok-ringkasan (lihat
 * ReportController::kartuStokSummaryQuery(), $row->days[]). Export ini
 * TIDAK pernah diimpor ulang (laporan baca-saja), jadi beda dari
 * OpnameGridWriter/SpoilGridWriter -- merge vertikal aman dipakai langsung
 * tanpa perlu trik "tulis ke 2 baris" (itu cuma wajib utk template yang
 * di-upload ulang lewat WithHeadingRow).
 */
class KartuStokRingkasanExport implements ShouldAutoSize, WithEvents, WithTitle
{
    private const FIXED_COLS = 5; // SKU, Nama Item, Kategori, Satuan, Saldo Awal

    /**
     * @param  Collection<int, object>  $rows
     */
    public function __construct(
        private readonly Collection $rows,
        private readonly Carbon $dateFrom,
        private readonly Carbon $dateTo,
    ) {
    }

    public function title(): string
    {
        return 'Kartu Stok Harian';
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();
                $firstRow = $this->rows->first();
                $days = $firstRow?->days ?? [];
                $lastCol = self::FIXED_COLS + (count($days) * 4) + 1; // +1 utk Saldo Akhir

                $sheet->setCellValue('A1', 'Kartu Stok Harian — IN / OUT / SPOIL / BAL per Tanggal');
                $sheet->setCellValue('A2', 'Periode: '.$this->dateFrom->format('d M Y').' s/d '.$this->dateTo->format('d M Y'));
                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(13);

                $headerRow = 4;
                $firstDataRow = $this->writeHeader($sheet, $headerRow, $days, $lastCol);
                $lastDataRow = $this->writeRows($sheet, $firstDataRow, $days);

                $lastColLetter = Coordinate::stringFromColumnIndex($lastCol);
                $sheet->freezePane('F'.$firstDataRow);
                if ($lastDataRow >= $firstDataRow) {
                    $sheet->setAutoFilter("A{$headerRow}:{$lastColLetter}".($headerRow + 1));
                }
            },
        ];
    }

    /**
     * @param  list<array{date:string,label:string,in:string,out:string,spoil:string,bal:string}>  $days
     * @return int  nomor baris pertama data item
     */
    private function writeHeader(Worksheet $sheet, int $headerRow, array $days, int $lastCol): int
    {
        $row2 = $headerRow + 1;

        $fixedLabels = ['SKU', 'Nama Item', 'Kategori', 'Satuan', 'Saldo Awal'];
        foreach ($fixedLabels as $i => $label) {
            $col = Coordinate::stringFromColumnIndex($i + 1);
            $sheet->setCellValue($col.$headerRow, $label);
            $sheet->mergeCells("{$col}{$headerRow}:{$col}{$row2}");
        }

        $colIndex = self::FIXED_COLS + 1;
        foreach ($days as $day) {
            $startCol = Coordinate::stringFromColumnIndex($colIndex);
            $endCol = Coordinate::stringFromColumnIndex($colIndex + 3);

            $sheet->setCellValue($startCol.$headerRow, $day['label']);
            $sheet->mergeCells("{$startCol}{$headerRow}:{$endCol}{$headerRow}");

            $sheet->setCellValue($startCol.$row2, 'IN');
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex + 1).$row2, 'OUT');
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex + 2).$row2, 'SPOIL');
            $sheet->setCellValue($endCol.$row2, 'BAL');

            $colIndex += 4;
        }

        $lastColLetter = Coordinate::stringFromColumnIndex($lastCol);
        $sheet->setCellValue($lastColLetter.$headerRow, 'Saldo Akhir');
        $sheet->mergeCells("{$lastColLetter}{$headerRow}:{$lastColLetter}{$row2}");

        $sheet->getStyle("A{$headerRow}:{$lastColLetter}{$row2}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1B4332']],
            'alignment' => ['horizontal' => 'center', 'vertical' => 'center', 'wrapText' => true],
        ]);

        return $row2 + 1;
    }

    private function writeRows(Worksheet $sheet, int $firstDataRow, array $dayTemplate): int
    {
        $row = $firstDataRow;

        foreach ($this->rows as $item) {
            $sheet->setCellValue('A'.$row, $item->canonical_sku);
            $sheet->setCellValue('B'.$row, $item->item_name);
            $sheet->setCellValue('C'.$row, $item->category_name ?? '-');
            $sheet->setCellValue('D'.$row, $item->unit ?? '-');
            $sheet->setCellValue('E'.$row, (float) $item->saldo_awal);

            $colIndex = self::FIXED_COLS + 1;
            foreach ($item->days as $day) {
                $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex).$row, (float) $day['in']);
                $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex + 1).$row, (float) $day['out']);
                $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex + 2).$row, (float) $day['spoil']);
                $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex + 3).$row, (float) $day['bal']);
                $colIndex += 4;
            }

            $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex).$row, (float) $item->saldo_akhir);

            $row++;
        }

        return $row - 1;
    }
}
