<?php

namespace App\Exports\Reports;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class StokMenipisExport implements FromArray, ShouldAutoSize, WithEvents
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
            ['Laporan Stok Menipis', 'Export: '.now()->format('d M Y H:i')],
            ['SKU', 'Item', 'Outlet', 'Kategori', 'Satuan', 'Qty Saat Ini', 'Min Stok', 'Kekurangan'],
        ], $rows->map(fn ($row): array => [
            $row->canonical_sku,
            $row->item_name,
            $row->outlet_name,
            $row->category,
            $row->unit,
            (float) $row->qty_on_hand,
            (float) $row->min_stock,
            (float) $row->kekurangan,
        ])->all());
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

                // Kekurangan (H) = Min Stok (G) - Qty Saat Ini (F), rumus
                // hidup supaya kalau qty diperbarui manual di Excel, sisanya
                // ikut ter-update.
                if ($this->dataRowCount > 0) {
                    $firstDataRow = 3;
                    $lastDataRow = 2 + $this->dataRowCount;

                    for ($r = $firstDataRow; $r <= $lastDataRow; $r++) {
                        $sheet->setCellValue("H{$r}", "=G{$r}-F{$r}");
                    }
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
            ->where('i.is_active', true)
            ->where('i.min_stock', '>', 0)
            ->whereRaw('sb.qty_on_hand < i.min_stock')
            ->when($this->filters['outlet_id'] ?? null, fn ($q, $id) => $q->where('sb.outlet_id', $id))
            ->when($this->filters['category_id'] ?? null, fn ($q, $id) => $q->where('i.item_category_id', $id))
            ->selectRaw('
                i.canonical_sku,
                i.name as item_name,
                i.min_stock,
                sb.qty_on_hand,
                o.name as outlet_name,
                COALESCE(u.abbreviation, \'pcs\') as unit,
                COALESCE(ic.name, \'Tanpa Kategori\') as category,
                ROUND(i.min_stock - sb.qty_on_hand, 6) as kekurangan
            ')
            ->orderByRaw('(i.min_stock - sb.qty_on_hand) DESC')
            ->orderBy('o.name')
            ->get();
    }
}
