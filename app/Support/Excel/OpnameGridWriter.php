<?php

namespace App\Support\Excel;

use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Menulis grid Opname persis layout form kertas:
 * No | Kode Item | ITEM | Satuan | SET OF (Ml|Gr|Pcs) | GUDANG (Balance) |
 * {AREA} (Utuh|Ecer|Total) | TOTAL (Per Satuan) | Departemen
 * dengan kategori sebagai baris pemisah (lihat CategoryDividerWriter).
 *
 * Header 2-baris: label di baris 1, sub-label unik di baris 2 -- importer
 * WAJIB pakai WithHeadingRow(2) karena baris 1 sebagian kosong/duplikat
 * akibat merge vertikal (No/Kode Item/ITEM/Satuan/Departemen). PhpSpreadsheet
 * cuma MENAMPILKAN cell top-left pada range yang di-merge -- cell lain tetap
 * punya nilai sendiri di file XLSX, jadi menulis label yang sama ke KEDUA
 * baris pada kolom yang di-merge vertikal aman dibaca ulang via heading row 2.
 */
final class OpnameGridWriter
{
    public const COL_NO = 'A';
    public const COL_KODE_ITEM = 'B';
    public const COL_ITEM = 'C';
    public const COL_SATUAN = 'D';
    public const COL_SET_OF_ML = 'E';
    public const COL_SET_OF_GR = 'F';
    public const COL_SET_OF_PCS = 'G';
    public const COL_GUDANG = 'H';
    public const COL_AREA_UTUH = 'I';
    public const COL_AREA_ECER = 'J';
    public const COL_AREA_TOTAL = 'K';
    public const COL_TOTAL_PER_SATUAN = 'L';
    public const COL_DEPARTEMEN = 'M';

    /**
     * @return int  nomor baris pertama untuk data item (headerRow + 2)
     */
    public static function writeHeader(Worksheet $sheet, int $headerRow, string $areaLabel): int
    {
        $row2 = $headerRow + 1;

        $sheet->setCellValue(self::COL_NO.$headerRow, 'No');
        $sheet->setCellValue(self::COL_NO.$row2, 'No');
        $sheet->mergeCells(self::COL_NO.$headerRow.':'.self::COL_NO.$row2);

        $sheet->setCellValue(self::COL_KODE_ITEM.$headerRow, 'Kode Item');
        $sheet->setCellValue(self::COL_KODE_ITEM.$row2, 'Kode Item');
        $sheet->mergeCells(self::COL_KODE_ITEM.$headerRow.':'.self::COL_KODE_ITEM.$row2);

        $sheet->setCellValue(self::COL_ITEM.$headerRow, 'ITEM');
        $sheet->setCellValue(self::COL_ITEM.$row2, 'ITEM');
        $sheet->mergeCells(self::COL_ITEM.$headerRow.':'.self::COL_ITEM.$row2);

        $sheet->setCellValue(self::COL_SATUAN.$headerRow, 'Satuan');
        $sheet->setCellValue(self::COL_SATUAN.$row2, 'Satuan');
        $sheet->mergeCells(self::COL_SATUAN.$headerRow.':'.self::COL_SATUAN.$row2);

        $sheet->setCellValue(self::COL_SET_OF_ML.$headerRow, 'SET OF');
        $sheet->mergeCells(self::COL_SET_OF_ML.$headerRow.':'.self::COL_SET_OF_PCS.$headerRow);
        $sheet->setCellValue(self::COL_SET_OF_ML.$row2, 'Ml');
        $sheet->setCellValue(self::COL_SET_OF_GR.$row2, 'Gr');
        $sheet->setCellValue(self::COL_SET_OF_PCS.$row2, 'Pcs');

        $sheet->setCellValue(self::COL_GUDANG.$headerRow, 'GUDANG');
        $sheet->setCellValue(self::COL_GUDANG.$row2, 'Balance');

        $sheet->setCellValue(self::COL_AREA_UTUH.$headerRow, $areaLabel);
        $sheet->mergeCells(self::COL_AREA_UTUH.$headerRow.':'.self::COL_AREA_TOTAL.$headerRow);
        $sheet->setCellValue(self::COL_AREA_UTUH.$row2, 'Utuh');
        $sheet->setCellValue(self::COL_AREA_ECER.$row2, 'Ecer');
        $sheet->setCellValue(self::COL_AREA_TOTAL.$row2, 'Total');

        $sheet->setCellValue(self::COL_TOTAL_PER_SATUAN.$headerRow, 'TOTAL');
        $sheet->setCellValue(self::COL_TOTAL_PER_SATUAN.$row2, 'Per Satuan');

        $sheet->setCellValue(self::COL_DEPARTEMEN.$headerRow, 'Departemen');
        $sheet->setCellValue(self::COL_DEPARTEMEN.$row2, 'Departemen');
        $sheet->mergeCells(self::COL_DEPARTEMEN.$headerRow.':'.self::COL_DEPARTEMEN.$row2);

        $sheet->getStyle(self::COL_NO.$headerRow.':'.self::COL_DEPARTEMEN.$row2)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1B4332']],
            'alignment' => ['horizontal' => 'center', 'vertical' => 'center', 'wrapText' => true],
        ]);

        return $row2 + 1;
    }

    /**
     * @param  list<array{
     *     category: string,
     *     kode_item: string,
     *     item_name: string,
     *     satuan: string,
     *     set_of_unit: ?string,
     *     set_of_qty: string|float|null,
     *     gudang_qty: string|float,
     *     area_utuh: string|int|float|null,
     *     area_ecer: string|int|float|null,
     *     department_label: string,
     * }>  $rows  area_utuh/area_ecer: null = kosongkan (mode import), angka = isi (mode laporan).
     * @return int  nomor baris terakhir yang ditulis
     */
    public static function writeRows(Worksheet $sheet, int $firstDataRow, array $rows): int
    {
        $row = $firstDataRow;
        $currentCategory = null;
        $no = 1;

        foreach ($rows as $data) {
            if ($data['category'] !== $currentCategory) {
                CategoryDividerWriter::write($sheet, $row, self::COL_NO, self::COL_DEPARTEMEN, $data['category']);
                $currentCategory = $data['category'];
                $row++;
            }

            $sheet->setCellValue(self::COL_NO.$row, $no++);
            $sheet->setCellValue(self::COL_KODE_ITEM.$row, $data['kode_item']);
            $sheet->setCellValue(self::COL_ITEM.$row, $data['item_name']);
            $sheet->setCellValue(self::COL_SATUAN.$row, $data['satuan']);

            if ($data['set_of_qty'] !== null) {
                $setOfCol = match (strtolower((string) $data['set_of_unit'])) {
                    'ml' => self::COL_SET_OF_ML,
                    'gr', 'g' => self::COL_SET_OF_GR,
                    'pcs', 'pc' => self::COL_SET_OF_PCS,
                    default => null,
                };

                if ($setOfCol !== null) {
                    $sheet->setCellValue($setOfCol.$row, (float) $data['set_of_qty']);
                }
            }

            $sheet->setCellValue(self::COL_GUDANG.$row, (float) $data['gudang_qty']);

            if ($data['area_utuh'] !== null) {
                $sheet->setCellValue(self::COL_AREA_UTUH.$row, $data['area_utuh']);
            }

            if ($data['area_ecer'] !== null) {
                $sheet->setCellValue(self::COL_AREA_ECER.$row, $data['area_ecer']);
            }

            $utuhCell = self::COL_AREA_UTUH.$row;
            $ecerCell = self::COL_AREA_ECER.$row;
            $ratioRange = self::COL_SET_OF_ML.$row.':'.self::COL_SET_OF_PCS.$row;
            $totalCell = self::COL_AREA_TOTAL.$row;
            $gudangCell = self::COL_GUDANG.$row;

            // Total area = Utuh * rasio SET OF (kalau ada, cuma 1 dari 3 sub-kolom
            // yang terisi per baris) + Ecer. Kalau Utuh & Ecer kosong (belum
            // dihitung), biarkan kosong -- BUKAN tampil 0.
            $sheet->setCellValue($totalCell, "=IF(AND({$utuhCell}=\"\",{$ecerCell}=\"\"),\"\",IF({$utuhCell}=\"\",0,{$utuhCell})*SUM({$ratioRange})+IF({$ecerCell}=\"\",0,{$ecerCell}))");

            $sheet->setCellValue(self::COL_TOTAL_PER_SATUAN.$row, "=IF({$totalCell}=\"\",\"\",{$gudangCell}+{$totalCell})");

            $sheet->setCellValue(self::COL_DEPARTEMEN.$row, $data['department_label']);

            $row++;
        }

        return $row - 1;
    }
}
