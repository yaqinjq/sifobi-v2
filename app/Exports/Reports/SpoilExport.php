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

class SpoilExport implements FromArray, ShouldAutoSize, WithEvents
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
            ['Laporan Spoil & Waste', 'Export: '.now()->format('d M Y H:i')],
            ['Waktu', 'SKU', 'Item', 'Outlet', 'Departemen', 'Qty', 'Unit', 'Qty Base', 'Alasan', 'Status', 'Foto Duplikat'],
        ], $rows->map(fn ($row): array => [
            Carbon::parse($row->recorded_at)->format('Y-m-d H:i'),
            $row->canonical_sku,
            $row->item_name,
            $row->outlet_name,
            $row->department_name,
            (float) $row->qty,
            $row->unit,
            (float) $row->qty_in_base_unit,
            $row->reason_category,
            $row->status,
            $row->is_duplicate_photo ? 'Ya' : 'Tidak',
        ])->all(), [
            ['TOTAL', '', '', '', '', '', '', (float) $rows->sum('qty_in_base_unit'), '', '', 'Duplikat: '.$rows->where('is_duplicate_photo', true)->count()],
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

        return DB::table('spoil_wastes as sw')
            ->join('items as i', 'i.id', '=', 'sw.item_id')
            ->join('outlets as o', 'o.id', '=', 'sw.outlet_id')
            ->leftJoin('departments as d', 'd.id', '=', 'sw.department_id')
            ->join('units as u', 'u.id', '=', 'sw.unit_id')
            ->where('sw.tenant_id', $this->tenantId)
            ->whereBetween('sw.recorded_date', [$dateFrom->toDateString(), $dateTo->toDateString()])
            ->when($this->filters['outlet_id'] ?? null, fn ($query, $outletId) => $query->where('sw.outlet_id', $outletId))
            ->when($this->filters['department_id'] ?? null, fn ($query, $departmentId) => $query->where('sw.department_id', $departmentId))
            ->select([
                'sw.recorded_at',
                'sw.qty',
                'sw.qty_in_base_unit',
                'sw.reason_category',
                'sw.status',
                'sw.is_duplicate_photo',
                'i.name as item_name',
                'i.canonical_sku',
                'o.name as outlet_name',
                'd.name as department_name',
                'u.abbreviation as unit',
            ])
            ->orderByDesc('sw.recorded_at')
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
