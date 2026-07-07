<?php

namespace App\Exports;

use App\Exports\Concerns\WithSifobiExcelStyles;
use App\Modules\Inventory\Models\Item;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class ItemsExport implements FromCollection, ShouldAutoSize, WithEvents, WithHeadings, WithMapping
{
    use WithSifobiExcelStyles;

    public function __construct(private readonly int $tenantId)
    {
    }

    public function collection(): Collection
    {
        return Item::query()
            ->with(['category', 'baseUnit', 'inventoryUnit', 'purchaseUnit'])
            ->where('tenant_id', $this->tenantId)
            ->orderBy('canonical_sku')
            ->get();
    }

    public function headings(): array
    {
        return [
            'canonical_sku',
            'name',
            'description',
            'item_category',
            'item_type',
            'base_unit',
            'inventory_unit',
            'purchase_unit',
            'inventory_ratio',
            'purchase_ratio',
            'yield_pct',
            'last_purchase_price',
            'is_active',
        ];
    }

    public function map(mixed $row): array
    {
        return [
            $row->canonical_sku,
            $row->name,
            $row->description,
            $row->category?->name,
            $row->item_type,
            $row->baseUnit?->code,
            $row->inventoryUnit?->code,
            $row->purchaseUnit?->code,
            $row->inventory_ratio,
            $row->purchase_ratio,
            $row->yield_pct,
            $row->last_purchase_price,
            $row->is_active ? 1 : 0,
        ];
    }
}

