<?php

namespace App\Exports\Reports;

use App\Modules\Operations\Models\OpenStock;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class OpenStockExport implements FromArray, ShouldAutoSize, WithEvents
{
    /**
     * @param  Collection<int, OpenStock>  $rows
     */
    public function __construct(
        private readonly Collection $rows,
        private readonly bool $showValue = true,
    ) {
    }

    public function array(): array
    {
        $headings = ['Tanggal', 'Outlet', 'Departemen', 'Item', 'SKU', 'Target', 'Qty Utuh', 'Qty Ecer', 'Qty Total', 'Unit'];

        if ($this->showValue) {
            $headings = array_merge($headings, ['HPP/Unit', 'Total Nilai']);
        }

        $headings[] = 'Status';

        $dataRows = $this->rows->map(function (OpenStock $row): array {
            $item = $row->item;
            $baseUnit = $item?->baseUnit?->abbreviation ?? $item?->baseUnit?->code ?? $row->unit?->code ?? '';
            $qtyTotal = (float) ($row->qty_in_base_unit ?? $row->qty_posted ?? 0);

            $line = [
                $row->business_date?->format('Y-m-d') ?? '',
                $row->outlet?->name ?? '',
                $row->department?->name ?? '',
                $item?->name ?? '',
                $item?->canonical_sku ?? '',
                $row->targetLabel(),
                (float) $row->qty_whole,
                (float) $row->qty_loose,
                $qtyTotal,
                $baseUnit,
            ];

            if ($this->showValue) {
                $costPerUnit = (float) ($row->cost_per_unit ?? 0);
                $line[] = $costPerUnit;
                $line[] = $costPerUnit * $qtyTotal;
            }

            $line[] = $row->status;

            return $line;
        })->all();

        return array_merge([
            ['Laporan Open Stock', 'Export: '.now()->format('d M Y H:i')],
            $headings,
        ], $dataRows);
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
}
