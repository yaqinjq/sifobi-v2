<?php

namespace App\Exports\Reports;

use App\Modules\Pos\Models\PosOrder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class PosSalesExport implements FromArray, ShouldAutoSize, WithEvents
{
    private int $dataRowCount = 0;

    public function __construct(
        private readonly int $tenantId,
        private readonly ?int $outletId,
        private readonly Carbon $dateFrom,
        private readonly Carbon $dateTo,
    ) {
    }

    public function array(): array
    {
        $rows = $this->rows();
        $this->dataRowCount = $rows->count();
        $grandTotal = $rows->sum('total_amount');

        $periodLabel = $this->dateFrom->isSameDay($this->dateTo)
            ? $this->dateFrom->format('d M Y')
            : $this->dateFrom->format('d M Y').' - '.$this->dateTo->format('d M Y');

        return array_merge([
            ['Laporan Penjualan POS', $periodLabel],
            ['No. Order', 'Outlet', 'Tipe', 'Tanggal Lunas', 'Metode Bayar', 'Total'],
        ], $rows->map(fn ($order): array => [
            $order->order_number,
            $order->outlet->name ?? '-',
            $order->order_type === PosOrder::TYPE_DINE_IN ? 'Dine-in' : 'Takeaway',
            Carbon::parse($order->closed_at)->format('Y-m-d H:i'),
            $order->payments->pluck('method')->unique()->implode(', '),
            (float) $order->total_amount,
        ])->all(), [
            ['TOTAL', '', '', '', '', (float) $grandTotal],
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

                if ($this->dataRowCount > 0) {
                    $firstDataRow = 3;
                    $lastDataRow = 2 + $this->dataRowCount;

                    $sheet->setCellValue("F{$highestRow}", "=SUM(F{$firstDataRow}:F{$lastDataRow})");
                }
            },
        ];
    }

    private function rows(): Collection
    {
        return PosOrder::query()
            ->where('tenant_id', $this->tenantId)
            ->where('status', PosOrder::STATUS_PAID)
            ->whereBetween('closed_at', [$this->dateFrom->copy()->startOfDay(), $this->dateTo->copy()->endOfDay()])
            ->when($this->outletId, fn ($q) => $q->where('outlet_id', $this->outletId))
            ->with(['outlet:id,name', 'payments:id,pos_order_id,method'])
            ->orderBy('closed_at')
            ->get();
    }
}
