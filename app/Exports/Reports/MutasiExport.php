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

class MutasiExport implements FromArray, ShouldAutoSize, WithEvents
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
        $totalIn = $rows->where('qty_change', '>', 0)->sum('qty_change');
        $totalOut = $rows->where('qty_change', '<', 0)->sum('qty_change');

        return array_merge([
            ['Laporan Mutasi Stok', 'Export: '.now()->format('d M Y H:i')],
            ['Waktu', 'Tipe', 'Target', 'SKU', 'Item', 'Outlet', 'Qty Change', 'Balance After', 'Unit', 'Catatan'],
        ], $rows->map(fn ($row): array => [
            Carbon::parse($row->performed_at)->format('Y-m-d H:i'),
            $row->mutation_type,
            $row->stock_target,
            $row->canonical_sku,
            $row->item_name,
            $row->outlet_name,
            (float) $row->qty_change,
            (float) $row->balance_after,
            $row->unit,
            $row->notes,
        ])->all(), [
            ['TOTAL', '', '', '', '', '', (float) ($totalIn + $totalOut), '', '', 'Masuk: '.$totalIn.' | Keluar: '.$totalOut],
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

        return DB::table('stock_mutations as sm')
            ->join('items as i', 'i.id', '=', 'sm.item_id')
            ->join('outlets as o', 'o.id', '=', 'sm.outlet_id')
            ->join('units as u', 'u.id', '=', 'sm.unit_id')
            ->where('sm.tenant_id', $this->tenantId)
            ->whereBetween('sm.performed_at', [$dateFrom, $dateTo])
            ->when($this->filters['outlet_id'] ?? null, fn ($query, $outletId) => $query->where('sm.outlet_id', $outletId))
            ->when($this->filters['item_id'] ?? null, fn ($query, $itemId) => $query->where('sm.item_id', $itemId))
            ->when($this->filters['mutation_type'] ?? null, fn ($query, $type) => $query->where('sm.mutation_type', $type))
            ->select([
                'sm.performed_at',
                'sm.mutation_type',
                'sm.stock_target',
                'sm.qty_change',
                'sm.balance_after',
                'sm.notes',
                'i.name as item_name',
                'i.canonical_sku',
                'o.name as outlet_name',
                'u.abbreviation as unit',
            ])
            ->orderByDesc('sm.performed_at')
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
