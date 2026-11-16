<?php

namespace App\Exports\Reports;

use App\Modules\Operations\Models\OpnameSession;
use App\Modules\Stock\Models\StockBalance;
use App\Modules\Stock\Models\StockMutation;
use App\Support\Excel\OpnameGridWriter;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;

/**
 * Laporan Opname 1 outlet untuk 1 tanggal -- 1 sheet per departemen yang
 * punya sesi PROCESSED tanggal itu, layout IDENTIK OpnameSessionFillTemplate
 * (reuse OpnameGridWriter) tapi kolom Utuh/Ecer diisi nilai hasil hitung
 * SUNGGUHAN (bukan kosong). GUDANG Balance = saldo warehouse LIVE saat
 * laporan di-generate, BUKAN snapshot historis tanggal laporan -- tidak ada
 * mekanisme ledger "as of date" di app ini (lihat catatan di sheet RINGKASAN).
 */
class OpnameOutletReportExport implements WithMultipleSheets
{
    public function __construct(
        private readonly int $tenantId,
        private readonly int $outletId,
        private readonly string $date,
        private readonly string $outletName,
    ) {
    }

    public function sheets(): array
    {
        $sessions = OpnameSession::query()
            ->where('tenant_id', $this->tenantId)
            ->where('outlet_id', $this->outletId)
            ->where('status', OpnameSession::STATUS_PROCESSED)
            ->whereDate('opname_date', $this->date)
            ->with(['department', 'items.item.category', 'items.item.inventoryUnit', 'items.item.baseUnit', 'items.department'])
            ->get();

        $itemIds = $sessions->flatMap(fn ($session) => $session->items->pluck('item_id'))->unique();

        $gudangBalances = StockBalance::query()
            ->where('tenant_id', $this->tenantId)
            ->where('outlet_id', $this->outletId)
            ->where('stock_target', StockMutation::TARGET_OUTLET_WAREHOUSE)
            ->whereIn('item_id', $itemIds)
            ->pluck('qty_on_hand', 'item_id')
            ->all();

        $sheets = [
            new OpnameOutletReportSummarySheet($this->outletName, $this->date, $sessions),
        ];

        foreach ($sessions as $session) {
            $sheets[] = new OpnameOutletReportDeptSheet($session, $gudangBalances);
        }

        return $sheets;
    }
}

class OpnameOutletReportSummarySheet implements FromArray, WithTitle
{
    public function __construct(
        private readonly string $outletName,
        private readonly string $date,
        private readonly Collection $sessions,
    ) {
    }

    public function array(): array
    {
        $rows = [
            ['Laporan Opname per Outlet'],
            ['Outlet', $this->outletName],
            ['Tanggal', $this->date],
            [],
            ['PENTING: kolom GUDANG Balance di tiap sheet departemen adalah saldo warehouse SAAT laporan ini di-generate, BUKAN saldo historis persis tanggal di atas -- aplikasi ini belum punya mekanisme snapshot ledger "as of tanggal".'],
            [],
            ['Departemen', 'Status'],
        ];

        foreach ($this->sessions as $session) {
            $rows[] = [$session->department?->name ?? 'Lintas Departemen', 'Ada data (lihat sheet terpisah)'];
        }

        if ($this->sessions->isEmpty()) {
            $rows[] = ['-', 'Tidak ada sesi opname PROCESSED untuk tanggal ini'];
        }

        return $rows;
    }

    public function title(): string
    {
        return 'RINGKASAN';
    }
}

class OpnameOutletReportDeptSheet implements FromArray, WithEvents, WithTitle
{
    /**
     * @param  array<int, string>  $gudangBalances
     */
    public function __construct(
        private readonly OpnameSession $session,
        private readonly array $gudangBalances,
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

                $sorted = $this->session->items->sortBy(
                    fn ($opnameItem) => $opnameItem->item?->formSortKey($opnameItem->department_id) ?? ''
                );

                $rows = [];

                foreach ($sorted as $opnameItem) {
                    $item = $opnameItem->item;

                    $rows[] = [
                        'category' => $item?->categoryForDepartment($opnameItem->department_id)?->name ?: 'Lainnya',
                        'kode_item' => $item?->canonical_sku ?? '-',
                        'item_name' => $item?->name ?? '-',
                        'satuan' => $item?->inventoryUnit?->abbreviation ?: $item?->baseUnit?->abbreviation ?: '-',
                        'set_of_unit' => $item?->baseUnit?->abbreviation,
                        'set_of_qty' => $item ? (string) $item->inventory_ratio : null,
                        'gudang_qty' => $this->gudangBalances[$opnameItem->item_id] ?? 0,
                        'area_utuh' => (float) $opnameItem->physical_qty_whole,
                        'area_ecer' => (float) $opnameItem->physical_qty_loose,
                        'department_label' => $opnameItem->department?->name ?? $item?->primaryDepartment?->name ?? '-',
                    ];
                }

                $areaLabel = $this->session->department?->name
                    ? strtoupper($this->session->department->name).' AREA'
                    : 'FISIK AREA';

                $firstDataRow = OpnameGridWriter::writeHeader($sheet, 1, $areaLabel);
                $sheet->freezePane('A'.$firstDataRow);
                OpnameGridWriter::writeRows($sheet, $firstDataRow, $rows);

                foreach (range('A', 'M') as $col) {
                    $sheet->getColumnDimension($col)->setAutoSize(true);
                }
            },
        ];
    }

    public function title(): string
    {
        $name = $this->session->department?->name ?? 'LINTAS-DEPT';
        $sanitized = preg_replace('/[:\\\\\/\?\*\[\]]/', '', $name);

        return substr($sanitized, 0, 31) ?: 'DEPT';
    }
}
