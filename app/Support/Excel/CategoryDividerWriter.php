<?php

namespace App\Support\Excel;

use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Baris pemisah kategori (merged cell, bold, bg abu-abu) -- dipakai semua
 * template/laporan Excel Opname & Spoil supaya kategori tampil sebagai BARIS
 * (persis form kertas), bukan kolom berulang di tiap baris item.
 */
final class CategoryDividerWriter
{
    public static function write(Worksheet $sheet, int $row, string $fromCol, string $toCol, string $name): void
    {
        $sheet->mergeCells("{$fromCol}{$row}:{$toCol}{$row}");
        $sheet->setCellValue("{$fromCol}{$row}", $name);
        $sheet->getStyle("{$fromCol}{$row}:{$toCol}{$row}")->applyFromArray([
            'font' => ['bold' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E5E7EB']],
        ]);
    }
}
