<?php

namespace App\Exports\Reports;

use App\Modules\Operations\Models\SpoilWaste;
use App\Support\Excel\SpoilGridWriter;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;

/**
 * Laporan Spoil & Waste 1 outlet untuk 1 rentang tanggal -- 1 sheet per
 * departemen yang punya catatan APPROVED dalam rentang itu, layout IDENTIK
 * SpoilWasteSessionFillTemplate (reuse SpoilGridWriter), TGL/STOK/NOTE diisi
 * data sungguhan dari SpoilWaste::qty/recorded_date/notes.
 */
class SpoilOutletReportExport implements WithMultipleSheets
{
    public function __construct(
        private readonly int $tenantId,
        private readonly int $outletId,
        private readonly string $dateFrom,
        private readonly string $dateTo,
        private readonly string $outletName,
    ) {
    }

    public function sheets(): array
    {
        $records = SpoilWaste::query()
            ->where('tenant_id', $this->tenantId)
            ->where('outlet_id', $this->outletId)
            ->where('status', SpoilWaste::STATUS_APPROVED)
            ->whereBetween('recorded_date', [$this->dateFrom, $this->dateTo])
            ->with(['item.category', 'item.inventoryUnit', 'item.baseUnit', 'menu.category', 'department'])
            ->get();

        $grouped = $records->groupBy(fn ($spoil) => $spoil->department?->name ?? 'Lintas Departemen');

        $sheets = [
            new SpoilOutletReportSummarySheet($this->outletName, $this->dateFrom, $this->dateTo, $grouped),
        ];

        foreach ($grouped as $departmentName => $departmentRecords) {
            $sheets[] = new SpoilOutletReportDeptSheet($departmentName, $departmentRecords);
        }

        return $sheets;
    }
}

class SpoilOutletReportSummarySheet implements FromArray, WithTitle
{
    public function __construct(
        private readonly string $outletName,
        private readonly string $dateFrom,
        private readonly string $dateTo,
        private readonly Collection $grouped,
    ) {
    }

    public function array(): array
    {
        $rows = [
            ['Laporan Spoil & Waste per Outlet'],
            ['Outlet', $this->outletName],
            ['Periode', "{$this->dateFrom} s/d {$this->dateTo}"],
            [],
            ['Departemen', 'Jumlah Catatan'],
        ];

        foreach ($this->grouped as $departmentName => $records) {
            $rows[] = [$departmentName, $records->count()];
        }

        if ($this->grouped->isEmpty()) {
            $rows[] = ['-', 'Tidak ada catatan spoil APPROVED di periode ini'];
        }

        return $rows;
    }

    public function title(): string
    {
        return 'RINGKASAN';
    }
}

class SpoilOutletReportDeptSheet implements FromArray, WithEvents, WithTitle
{
    public function __construct(
        private readonly string $departmentName,
        private readonly Collection $records,
    ) {
    }

    public function array(): array
    {
        return [[]];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();

                // Spoil menu (tanpa item) ditaruh setelah semua bahan baku,
                // dikelompokkan per kategori menu.
                $sorted = $this->records->sortBy(
                    fn ($spoil) => $spoil->item?->formSortKey(null)
                        ?? '~'.($spoil->menu?->category?->name ?? '').' '.($spoil->menu?->name ?? '')
                );

                $rows = [];

                foreach ($sorted as $spoil) {
                    $item = $spoil->item;

                    if ($item === null && $spoil->menu !== null) {
                        $rows[] = [
                            'category' => 'MENU - '.($spoil->menu->category?->name ?? 'Lainnya'),
                            'kode_item' => $spoil->menu->code ?? '-',
                            'item_name' => $spoil->menu->name,
                            'satuan' => 'porsi',
                            'tgl' => optional($spoil->recorded_date)->toDateString(),
                            'stok' => (float) $spoil->qty,
                            'price_per_unit' => (float) ($spoil->menu->selling_price ?? 0),
                            'reason_category' => $spoil->reason_category,
                            'note' => $spoil->notes,
                        ];

                        continue;
                    }

                    $rows[] = [
                        'category' => $item?->categoryForDepartment(null)?->name ?: 'Lainnya',
                        'kode_item' => $item?->canonical_sku ?? '-',
                        'item_name' => $item?->name ?? '-',
                        'satuan' => $item?->inventoryUnit?->abbreviation ?: $item?->baseUnit?->abbreviation ?: '-',
                        'tgl' => optional($spoil->recorded_date)->toDateString(),
                        'stok' => (float) $spoil->qty,
                        'price_per_unit' => $item?->standard_cost ?: $item?->last_purchase_price ?: 0,
                        'reason_category' => $spoil->reason_category,
                        'note' => $spoil->notes,
                    ];
                }

                $firstDataRow = SpoilGridWriter::writeHeader($sheet, 1);
                $sheet->freezePane('A'.$firstDataRow);
                SpoilGridWriter::writeRows($sheet, $firstDataRow, $rows);

                foreach (range('A', 'I') as $col) {
                    $sheet->getColumnDimension($col)->setAutoSize(true);
                }
            },
        ];
    }

    public function title(): string
    {
        $sanitized = preg_replace('/[:\\\\\/\?\*\[\]]/', '', $this->departmentName);

        return substr($sanitized, 0, 31) ?: 'DEPT';
    }
}
