<?php

namespace App\Exports\Reports;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class PenerimaanExport implements FromArray, ShouldAutoSize, WithEvents
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function __construct(
        private readonly int $tenantId,
        private readonly array $filters = []
    ) {
    }

    public function array(): array
    {
        $rows = $this->rows();

        return array_merge([
            ['Laporan Penerimaan Barang', 'Export: '.now()->format('d M Y H:i')],
            ['Kode GR', 'Tanggal', 'Sumber', 'Supplier', 'Outlet', 'SKU', 'Item', 'Qty', 'Unit', 'Harga', 'Total', 'Status'],
        ], $rows->map(fn ($row): array => [
            $row->code,
            Carbon::parse($row->receipt_date)->format('Y-m-d'),
            $row->source,
            $row->supplier_name ?: $row->vendor_name,
            $row->outlet_name,
            $row->canonical_sku,
            $row->item_name,
            (float) $row->qty_received,
            $row->unit,
            (float) $row->unit_price,
            (float) $row->total_value,
            $row->status,
        ])->all(), [
            ['TOTAL', '', '', '', '', '', '', '', '', '', (float) $rows->sum('total_value'), ''],
        ]);
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();
                $highestColumn = $sheet->getHighestColumn();
                $highestRow = $sheet->getHighestRow();

                $sheet->freezePane('A3');
                $sheet->getStyle("A1:{$highestColumn}1")->getFont()->setBold(true);
                $sheet->getStyle("A2:{$highestColumn}2")->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1B4332']],
                ]);
                $sheet->getStyle("A{$highestRow}:{$highestColumn}{$highestRow}")->getFont()->setBold(true);
            },
        ];
    }

    private function rows(): Collection
    {
        [$dateFrom, $dateTo] = $this->dateRange();

        return DB::table('goods_receipts as gr')
            ->join('goods_receipt_items as gri', 'gri.goods_receipt_id', '=', 'gr.id')
            ->join('items as i', 'i.id', '=', 'gri.item_id')
            ->join('outlets as o', 'o.id', '=', 'gr.outlet_id')
            ->join('units as u', 'u.id', '=', 'gri.unit_id')
            ->where('gr.tenant_id', $this->tenantId)
            ->whereBetween('gr.receipt_date', [$dateFrom->toDateString(), $dateTo->toDateString()])
            ->when($this->filters['outlet_id'] ?? null, fn ($query, $outletId) => $query->where('gr.outlet_id', $outletId))
            ->when($this->filters['source'] ?? null, fn ($query, $source) => $query->where('gr.source', $source))
            ->select([
                'gr.code',
                'gr.receipt_date',
                'gr.source',
                'gr.supplier_name',
                'gr.vendor_name',
                'gr.status',
                'o.name as outlet_name',
                'i.name as item_name',
                'i.canonical_sku',
                'u.abbreviation as unit',
                'gri.qty_received',
                'gri.unit_price',
                'gri.total_value',
            ])
            ->orderByDesc('gr.receipt_date')
            ->orderByDesc('gr.id')
            ->get();
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function dateRange(): array
    {
        return [
            isset($this->filters['date_from']) ? Carbon::parse($this->filters['date_from'])->startOfDay() : now()->startOfMonth(),
            isset($this->filters['date_to']) ? Carbon::parse($this->filters['date_to'])->endOfDay() : now()->endOfDay(),
        ];
    }
}
