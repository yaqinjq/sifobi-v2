<?php

namespace App\Exports\Reports;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class KartuStokRingkasanExport implements FromArray, ShouldAutoSize, WithEvents
{
    /**
     * @param  Collection<int, object>  $rows
     */
    public function __construct(
        private readonly Collection $rows,
        private readonly Carbon $dateFrom,
        private readonly Carbon $dateTo,
    ) {
    }

    public function array(): array
    {
        return array_merge([
            ['Kartu Stok — Ringkasan Semua Item'],
            ['Periode: '.$this->dateFrom->format('d M Y').' s/d '.$this->dateTo->format('d M Y')],
            ['SKU', 'Nama Item', 'Satuan', 'Saldo Awal', 'Total Masuk', 'Total Keluar', 'Saldo Akhir', 'Jumlah Transaksi'],
        ], $this->rows->map(fn ($row): array => [
            $row->canonical_sku,
            $row->item_name,
            $row->unit ?? '-',
            (float) $row->saldo_awal,
            (float) $row->total_masuk,
            (float) $row->total_keluar,
            (float) $row->saldo_akhir,
            (int) $row->jumlah_transaksi,
        ])->all());
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();
                $highestColumn = $sheet->getHighestColumn();

                $sheet->freezePane('A4');
                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(13);
                $sheet->getStyle("A3:{$highestColumn}3")->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1B4332']],
                ]);
            },
        ];
    }
}
