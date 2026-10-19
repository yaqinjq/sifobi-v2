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
 * observasi fisik, BUKAN laporan saldo/ledger -- TIDAK ADA baris total
 * yang menjumlahkan qty lintas item/unit berbeda.
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
            ['Tanggal', 'Outlet', 'Departemen', 'Template', 'Status Dokumen', 'Item', 'Subkategori', 'Lokasi', 'Qty Utuh', 'Qty Ecer', 'Satuan Utuh', 'Satuan Ecer', 'Status Hitung', 'Catatan'],
        ], $rows->map(fn ($row): array => [
            $row->business_date,
            $this->sanitize($row->outlet_name),
            $this->sanitize($row->department_name),
            $this->sanitize($row->template_name),
            $row->status,
            $this->sanitize($row->item_name),
            $this->sanitize($row->subcategory_label),
            $row->location,
            // Draft/belum dihitung TIDAK PERNAH ditampilkan sebagai 0 --
            // beda antara "belum disentuh" (is_counted=false, cell kosong/
            // label eksplisit) vs "sengaja diisi 0" (is_counted=true,
            // angka 0 sungguhan) harus tetap jelas di laporan Finance.
            $row->is_counted ? $row->qty_whole : '(belum dihitung)',
            $row->is_counted && $row->qty_loose !== null ? $row->qty_loose : ($row->is_counted ? '' : '(belum dihitung)'),
            $row->whole_unit ?? '',
            $row->loose_unit ?? '',
            $row->is_counted ? 'Sudah' : 'Belum',
            $this->sanitize($row->notes),
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
            ->join('outlets as o', 'o.id', '=', 'd.outlet_id')
            ->join('departments as dept', 'dept.id', '=', 'd.department_id')
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
                'o.name as outlet_name',
                'dept.name as department_name',
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

    /**
     * Cegah formula injection (CSV/Excel) -- kalau teks (nama item, catatan,
     * dst) diawali karakter yang ditafsirkan Excel/Sheets sebagai awal
     * formula (=, +, -, @, tab, CR), tambahkan apostrof di depan supaya
     * sel dipaksa dibaca sebagai teks murni, bukan dieksekusi sebagai
     * formula saat file dibuka di Excel/Sheets.
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
