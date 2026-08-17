<?php

namespace App\Exports\Reports;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class StokSummaryExport implements FromArray, ShouldAutoSize, WithEvents
{
    private int $dataRowCount = 0;

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
        $this->dataRowCount = $rows->count();

        return array_merge([
            ['Ringkasan Stok Semua Outlet', 'Export: '.now()->format('d M Y H:i')],
            ['Outlet', 'SKU', 'Item', 'Kategori', 'Satuan', 'Qty Saat Ini', 'Avg Cost', 'Total Nilai'],
        ], $rows->map(fn ($row): array => [
            $row->outlet_name,
            $row->canonical_sku,
            $row->item_name,
            $row->category,
            $row->unit,
            (float) $row->qty_on_hand,
            (float) $row->avg_cost,
            (float) $row->total_value,
        ])->all(), [
            ['TOTAL', '', '', '', '', '', '', (float) $rows->sum('total_value')],
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

                // Total Nilai (H) = Qty (F) x Avg Cost (G) per baris, dan
                // baris TOTAL = SUM — rumus hidup, bukan angka statis.
                if ($this->dataRowCount > 0) {
                    $firstDataRow = 3;
                    $lastDataRow = 2 + $this->dataRowCount;

                    for ($r = $firstDataRow; $r <= $lastDataRow; $r++) {
                        $sheet->setCellValue("H{$r}", "=F{$r}*G{$r}");
                    }

                    $sheet->setCellValue("H{$highestRow}", "=SUM(H{$firstDataRow}:H{$lastDataRow})");
                }
            },
        ];
    }

    private function rows(): Collection
    {
        return DB::table('stock_balances as sb')
            ->join('items as i', 'i.id', '=', 'sb.item_id')
            ->join('outlets as o', 'o.id', '=', 'sb.outlet_id')
            ->leftJoin('units as u', 'u.id', '=', 'i.inventory_unit_id')
            ->leftJoin('item_categories as ic', 'ic.id', '=', 'i.item_category_id')
            ->where('sb.tenant_id', $this->tenantId)
            ->where('sb.qty_on_hand', '>', 0)
            ->when($this->filters['outlet_id'] ?? null, fn ($q, $id) => $q->where('sb.outlet_id', $id))
            ->when($this->filters['brand_id'] ?? null, fn ($q, $id) => $q->where('o.brand_id', $id))
            ->when($this->filters['category_id'] ?? null, fn ($q, $id) => $q->where('i.item_category_id', $id))
            ->selectRaw('
                o.name as outlet_name,
                i.canonical_sku,
                i.name as item_name,
                COALESCE(ic.name, \'Tanpa Kategori\') as category,
                COALESCE(u.abbreviation, \'pcs\') as unit,
                sb.qty_on_hand,
                sb.avg_cost,
                sb.total_value
            ')
            ->orderBy('o.name')
            ->orderBy('i.name')
            ->get();
    }
}
