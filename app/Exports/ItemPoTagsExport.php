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

class ItemPoTagsExport implements FromCollection, ShouldAutoSize, WithEvents, WithHeadings, WithMapping
{
    use WithSifobiExcelStyles;

    public function __construct(private readonly int $tenantId)
    {
    }

    public function collection(): Collection
    {
        return Item::query()
            ->with('inventoryUnit')
            ->where('tenant_id', $this->tenantId)
            ->where('is_active', true)
            ->orderBy('canonical_sku')
            ->get();
    }

    public function headings(): array
    {
        return ['canonical_sku', 'nama_item', 'satuan_inventory', 'tujuan_po'];
    }

    /**
     * @param  Item  $row
     */
    public function map(mixed $row): array
    {
        $destinations = $row->po_destinations ?? [];

        return [
            $row->canonical_sku,
            $row->name,
            $row->inventoryUnit?->code ?? '',
            implode('|', $destinations),
        ];
    }
}
