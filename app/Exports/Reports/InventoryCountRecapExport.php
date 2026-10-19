<?php

namespace App\Exports\Reports;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * Export Rekap Hasil Hitung Mingguan -- READ-ONLY, format panjang (1 baris
 * per item x lokasi x tanggal) supaya tidak ada ambiguitas penjumlahan
 * lintas unit/item berbeda (TIDAK ADA baris total apa pun). Mengikuti
 * scope+filter yang SAMA dengan tampilan (controller yang membangun
 * $rows, bukan query bebas di sini). Qty Utuh/Ecer dan satuannya
 * dipisah jelas, hari yang belum dihitung ditandai eksplisit, BUKAN
 * ditampilkan sebagai 0.
 */
class InventoryCountRecapExport implements FromArray, ShouldAutoSize, WithEvents
{
    /**
     * @param  Collection<int, array>  $rows
     */
    public function __construct(
        private readonly Collection $rows,
        private readonly Carbon $weekStart
    ) {
    }

    public function array(): array
    {
        $out = [
            ['Rekap Hasil Hitung Mingguan', 'Pendataan fisik -- belum mengubah saldo stok sistem. Hari kosong = belum dihitung, bukan 0.'],
            ['Item', 'Subkategori', 'Lokasi', 'Tanggal', 'Status Hitung', 'Qty Utuh', 'Satuan Utuh', 'Qty Ecer', 'Satuan Ecer', 'Catatan'],
        ];

        foreach ($this->rows as $row) {
            $itemName = $this->sanitize($row['item']->name ?? '-');
            $subcategory = $this->sanitize($row['subcategory']);
            $location = $this->sanitize($row['location']);

            foreach ($row['days'] as $day) {
                $out[] = [
                    $itemName,
                    $subcategory,
                    $location,
                    $day['date'],
                    $day['counted'] ? 'Sudah' : 'Belum dihitung',
                    $day['counted'] ? $day['qty_whole'] : '(belum dihitung)',
                    $day['whole_unit'] ?? '',
                    $day['counted'] && $day['qty_loose'] !== null ? $day['qty_loose'] : ($day['counted'] ? '' : '(belum dihitung)'),
                    $day['loose_unit'] ?? '',
                    // Angka tetap apa adanya (TIDAK diubah/di-nol-kan) --
                    // kolom ini cuma penanda, supaya konsisten dgn peringatan
                    // "satuan belum valid" yang sudah ada di tampilan web
                    // (lihat recap-row.blade.php). Tanpa ini, pembaca Excel
                    // bisa salah percaya qty+satuan sudah pasti benar padahal
                    // konfigurasi konversi/override satuannya belum valid.
                    ($day['counted'] && ! $day['config_valid']) ? 'Satuan belum valid, cek konfigurasi item' : '',
                ];
            }
        }

        return $out;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();
                $highestColumn = $sheet->getHighestColumn();

                $sheet->freezePane('A3');
                $sheet->getStyle("A1:{$highestColumn}1")->getFont()->setBold(true);
                $sheet->getStyle("A2:{$highestColumn}2")->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1B4332']],
                ]);
            },
        ];
    }

    /**
     * Cegah formula injection (CSV/Excel) -- lihat InventoryCountExport
     * utk penjelasan lengkap, pola identik.
     */
    private function sanitize(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }

        if (preg_match('/^[=+\-@\t\r]/', $value)) {
            return "'".$value;
        }

        return $value;
    }
}
