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

/**
 * Export hasil hitung fisik Inventory Simple -- SELALU di-scope tenant +
 * outlet + departemen yang dipanggil (dipaksa oleh controller, tidak
 * pernah dari filter bebas client untuk non-superadmin). Murni laporan
 * observasi fisik, BUKAN laporan saldo/ledger.
 */
class InventoryCountExport implements FromArray, ShouldAutoSize, WithEvents
{
    private int $dataRowCount = 0;

    /**
     * @param  array<string, mixed>  $filters
     */
    public function __construct(
        private readonly int $tenantId,
        private readonly int $outletId,
        private readonly int $departmentId,
        private readonly array $filters = []
    ) {
    }

    public function array(): array
    {
        $rows = $this->rows();
        $this->dataRowCount = $rows->count();

        return array_merge([
            ['Export Inventory Simple', 'Pendataan fisik -- belum mengubah saldo stok sistem.'],
            ['Tanggal', 'Template', 'Status', 'Item', 'Subkategori', 'Lokasi', 'Qty Utuh', 'Qty Ecer', 'Satuan Utuh', 'Satuan Ecer', 'Sudah Dihitung', 'Catatan'],
        ], $rows->map(fn ($row): array => [
            $row->business_date,
            $row->template_name,
            $row->status,
            $row->item_name,
            $row->subcategory_label,
            $row->location,
            $row->qty_whole,
            $row->qty_loose,
            $row->whole_unit ?? '',
            $row->loose_unit ?? '',
            $row->is_counted ? 'Ya' : 'Belum',
            $row->notes,
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
            },
        ];
    }

    private function rows(): Collection
    {
        [$dateFrom, $dateTo] = $this->dateRange();

        return DB::table('inventory_count_documents as d')
            ->join('inventory_count_lines as l', 'l.document_id', '=', 'd.id')
            ->join('inventory_count_templates as t', 't.id', '=', 'd.template_id')
            ->join('items as i', 'i.id', '=', 'l.item_id')
            ->leftJoin('units as wu', 'wu.id', '=', 'l.whole_unit_id')
            ->leftJoin('units as lu', 'lu.id', '=', 'l.loose_unit_id')
            ->where('d.tenant_id', $this->tenantId)
            ->where('d.outlet_id', $this->outletId)
            ->where('d.department_id', $this->departmentId)
            ->whereBetween('d.business_date', [$dateFrom->toDateString(), $dateTo->toDateString()])
            ->when($this->filters['template_id'] ?? null, fn ($q, $templateId) => $q->where('d.template_id', $templateId))
            ->when($this->filters['status'] ?? null, fn ($q, $status) => $q->where('d.status', $status))
            ->select([
                'd.business_date',
                't.name as template_name',
                'd.status',
                'i.name as item_name',
                'l.subcategory_label',
                'l.location',
                'l.qty_whole',
                'l.qty_loose',
                'wu.abbreviation as whole_unit',
                'lu.abbreviation as loose_unit',
                'l.is_counted',
                'l.notes',
            ])
            ->orderByDesc('d.business_date')
            ->orderBy('l.sort_order')
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
