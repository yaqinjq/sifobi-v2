<?php

namespace App\Support\Excel;

use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Menulis grid Spoil & Waste persis layout form kertas:
 * TGL | Kode Item | ITEM | Satuan | STOK | PRICE/UNIT | TOTAL HARGA | NOTE
 * dengan kategori sebagai baris pemisah (lihat CategoryDividerWriter).
 * Beda dari OpnameGridWriter: header 1 baris saja (tidak ada sub-kolom
 * bercabang), jadi WithHeadingRow default (baris 1) cukup untuk importer.
 */
final class SpoilGridWriter
{
    public const COL_TGL = 'A';
    public const COL_KODE_ITEM = 'B';
    public const COL_ITEM = 'C';
    public const COL_SATUAN = 'D';
    public const COL_STOK = 'E';
    public const COL_PRICE_PER_UNIT = 'F';
    public const COL_TOTAL_HARGA = 'G';
    public const COL_KATEGORI_ALASAN = 'H';
    public const COL_NOTE = 'I';

    public static function writeHeader(Worksheet $sheet, int $headerRow): int
    {
        $sheet->setCellValue(self::COL_TGL.$headerRow, 'TGL');
        $sheet->setCellValue(self::COL_KODE_ITEM.$headerRow, 'Kode Item');
        $sheet->setCellValue(self::COL_ITEM.$headerRow, 'ITEM');
        $sheet->setCellValue(self::COL_SATUAN.$headerRow, 'Satuan');
        $sheet->setCellValue(self::COL_STOK.$headerRow, 'STOK');
        $sheet->setCellValue(self::COL_PRICE_PER_UNIT.$headerRow, 'PRICE/UNIT');
        $sheet->setCellValue(self::COL_TOTAL_HARGA.$headerRow, 'TOTAL HARGA');
        $sheet->setCellValue(self::COL_KATEGORI_ALASAN.$headerRow, 'Kategori Alasan');
        $sheet->setCellValue(self::COL_NOTE.$headerRow, 'NOTE');

        $sheet->getStyle(self::COL_TGL.$headerRow.':'.self::COL_NOTE.$headerRow)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1B4332']],
        ]);

        return $headerRow + 1;
    }

    /**
     * @param  list<array{
     *     category: string,
     *     kode_item: string,
     *     item_name: string,
     *     satuan: string,
     *     tgl: ?string,
     *     stok: string|int|float|null,
     *     price_per_unit: string|float,
     *     reason_category: ?string,
     *     note: ?string,
     * }>  $rows  tgl/stok: null = kosongkan (mode import), isi = mode laporan.
     * @return int  nomor baris terakhir yang ditulis
     */
    public static function writeRows(Worksheet $sheet, int $firstDataRow, array $rows): int
    {
        $row = $firstDataRow;
        $currentCategory = null;

        foreach ($rows as $data) {
            if ($data['category'] !== $currentCategory) {
                CategoryDividerWriter::write($sheet, $row, self::COL_TGL, self::COL_NOTE, $data['category']);
                $currentCategory = $data['category'];
                $row++;
            }

            if ($data['tgl'] !== null) {
                $sheet->setCellValue(self::COL_TGL.$row, $data['tgl']);
            }

            $sheet->setCellValue(self::COL_KODE_ITEM.$row, $data['kode_item']);
            $sheet->setCellValue(self::COL_ITEM.$row, $data['item_name']);
            $sheet->setCellValue(self::COL_SATUAN.$row, $data['satuan']);

            if ($data['stok'] !== null) {
                $sheet->setCellValue(self::COL_STOK.$row, $data['stok']);
            }

            $sheet->setCellValue(self::COL_PRICE_PER_UNIT.$row, (float) $data['price_per_unit']);

            $stokCell = self::COL_STOK.$row;
            $priceCell = self::COL_PRICE_PER_UNIT.$row;
            $sheet->setCellValue(self::COL_TOTAL_HARGA.$row, "=IF({$stokCell}=\"\",\"\",ROUND({$stokCell}*{$priceCell},2))");

            if ($data['reason_category'] !== null) {
                $sheet->setCellValue(self::COL_KATEGORI_ALASAN.$row, $data['reason_category']);
            }

            if ($data['note'] !== null) {
                $sheet->setCellValue(self::COL_NOTE.$row, $data['note']);
            }

            $row++;
        }

        return $row - 1;
    }
}
