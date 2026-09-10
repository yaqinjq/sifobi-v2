<?php

namespace App\Exports\Reports;

use App\Modules\Inventory\Models\Item;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class KartuStokDetailExport implements FromArray, ShouldAutoSize, WithEvents
{
    /**
     * @param  array{saldo_awal: string, saldo_akhir: string, mutations: \Illuminate\Support\Collection<int, object>}  $card
     */
    public function __construct(
        private readonly Item $item,
        private readonly array $card,
        private readonly Carbon $dateFrom,
        private readonly Carbon $dateTo,
    ) {
    }

    public function array(): array
    {
        return array_merge([
            ['Kartu Stok — '.$this->item->name.' ('.$this->item->canonical_sku.')'],
            ['Periode: '.$this->dateFrom->format('d M Y').' s/d '.$this->dateTo->format('d M Y')],
            ['Saldo Awal', (float) $this->card['saldo_awal']],
            [],
            ['Tanggal', 'Jenis Mutasi', 'Target', 'Masuk', 'Keluar', 'Saldo Berjalan', 'Referensi', 'Catatan'],
        ], $this->card['mutations']->map(fn ($row): array => [
            Carbon::parse($row->performed_at)->format('Y-m-d H:i'),
            $row->mutation_type_label,
            $row->stock_target,
            (float) $row->qty_change > 0 ? (float) $row->qty_change : 0,
            (float) $row->qty_change < 0 ? (float) $row->qty_change : 0,
            (float) $row->saldo_berjalan,
            $row->reference_type ? class_basename($row->reference_type).' #'.$row->reference_id : '-',
            $row->notes,
        ])->all(), [
            ['', '', '', '', 'SALDO AKHIR', (float) $this->card['saldo_akhir'], '', ''],
        ]);
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();
                $highestColumn = $sheet->getHighestColumn();
                $highestRow = $sheet->getHighestRow();

                $sheet->freezePane('A6');
                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(13);
                $sheet->getStyle("A5:{$highestColumn}5")->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1B4332']],
                ]);
                $sheet->getStyle("A{$highestRow}:{$highestColumn}{$highestRow}")->getFont()->setBold(true);
            },
        ];
    }
}
