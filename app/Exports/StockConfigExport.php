<?php

namespace App\Exports;

use App\Exports\Concerns\WithSifobiExcelStyles;
use App\Modules\Inventory\Models\ItemOutlet;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class StockConfigExport implements FromCollection, ShouldAutoSize, WithEvents, WithHeadings, WithMapping
{
    use WithSifobiExcelStyles;

    public function __construct(private readonly int $tenantId)
    {
    }

    public function collection(): Collection
    {
        return ItemOutlet::query()
            ->with(['item', 'outlet', 'unit'])
            ->where('tenant_id', $this->tenantId)
            ->orderBy('outlet_id')
            ->orderBy('item_id')
            ->get();
    }

    public function headings(): array
    {
        return ['item_sku', 'item_name', 'outlet_code', 'outlet_name', 'min_stock_qty', 'max_stock_qty', 'reorder_point', 'unit_code'];
    }

    public function map(mixed $row): array
    {
        return [
            $row->item?->canonical_sku,
            $row->item?->name,
            $row->outlet?->code,
            $row->outlet?->name,
            $row->min_stock,
            $row->max_stock,
            $row->reorder_point ?? $row->par_stock,
            $row->unit?->code ?? $row->item?->baseUnit?->code ?? $row->item?->inventoryUnit?->code,
        ];
    }
}

