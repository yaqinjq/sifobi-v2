<?php

namespace App\Exports\Templates;

use App\Modules\Core\Models\Department;
use App\Modules\Inventory\Models\Item;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class OpenStockTemplate implements WithMultipleSheets
{
    public function __construct(private readonly int $tenantId)
    {
    }

    public function sheets(): array
    {
        return [
            new OpenStockTemplateSheet(),
            new OpenStockInstructionSheet(),
            new OpenStockItemListSheet($this->tenantId),
            new OpenStockDepartmentListSheet($this->tenantId),
        ];
    }
}

class OpenStockTemplateSheet implements FromArray, ShouldAutoSize, WithEvents, WithStyles, WithTitle
{
    public function array(): array
    {
        return [
            ['tanggal_stok_awal', 'item_sku', 'departemen_code', 'target', 'qty_whole', 'qty_loose', 'catatan'],
            ['2026-06-29', 'MKO-AJINOMOTO-500GR', 'BAR', 'STOK_HARIAN_OUTLET', '2', '350', 'Sisa kemarin'],
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => $this->headerStyle(),
            2 => [
                'font' => [
                    'italic' => true,
                    'color' => ['rgb' => '6B7280'],
                ],
            ],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $event->sheet->getDelegate()->freezePane('A2');
            },
        ];
    }

    public function title(): string
    {
        return 'Open Stock';
    }

    private function headerStyle(): array
    {
        return [
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '1B4332'],
            ],
        ];
    }
}

class OpenStockInstructionSheet implements FromArray, ShouldAutoSize, WithStyles, WithTitle
{
    public function array(): array
    {
        return [
            ['Kolom', 'Keterangan', 'Contoh'],
            ['tanggal_stok_awal', 'Format YYYY-MM-DD atau DD/MM/YYYY', '2026-06-29'],
            ['item_sku', 'SKU canonical dari master item', 'MKO-AJINOMOTO-500GR'],
            ['departemen_code', 'Kode departemen operasional', 'BAR'],
            ['target', 'STOK_HARIAN_OUTLET atau GUDANG_UTAMA', 'STOK_HARIAN_OUTLET'],
            ['qty_whole', 'Jumlah dalam satuan utuh/inventory atau pembelian', '2'],
            ['qty_loose', 'Jumlah ecer dalam satuan dasar, isi 0 untuk gudang utama', '350'],
            ['catatan', 'Catatan opsional per baris', 'Sisa dari shift kemarin'],
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => [
                    'bold' => true,
                    'color' => ['rgb' => 'FFFFFF'],
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '1B4332'],
                ],
            ],
        ];
    }

    public function title(): string
    {
        return 'PETUNJUK';
    }
}

class OpenStockItemListSheet implements FromArray, ShouldAutoSize, WithStyles, WithTitle
{
    public function __construct(private readonly int $tenantId)
    {
    }

    public function array(): array
    {
        $rows = [['SKU', 'Nama', 'Satuan Dasar', 'Satuan Inventory']];

        Item::query()
            ->with(['baseUnit', 'inventoryUnit'])
            ->where('tenant_id', $this->tenantId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->each(function (Item $item) use (&$rows): void {
                $rows[] = [
                    $item->canonical_sku,
                    $item->name,
                    $item->baseUnit?->code,
                    $item->inventoryUnit?->code,
                ];
            });

        return $rows;
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => [
                    'bold' => true,
                    'color' => ['rgb' => 'FFFFFF'],
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '1B4332'],
                ],
            ],
        ];
    }

    public function title(): string
    {
        return 'Daftar SKU Item';
    }
}

class OpenStockDepartmentListSheet implements FromArray, ShouldAutoSize, WithStyles, WithTitle
{
    public function __construct(private readonly int $tenantId)
    {
    }

    public function array(): array
    {
        $rows = [['Code', 'Nama']];

        Department::query()
            ->where('tenant_id', $this->tenantId)
            ->where('status', 'ACTIVE')
            ->orderBy('name')
            ->get()
            ->each(function (Department $department) use (&$rows): void {
                $rows[] = [$department->code, $department->name];
            });

        return $rows;
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => [
                    'bold' => true,
                    'color' => ['rgb' => 'FFFFFF'],
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '1B4332'],
                ],
            ],
        ];
    }

    public function title(): string
    {
        return 'Daftar Departemen';
    }
}
