<?php

namespace App\Exports;

use App\Exports\Concerns\WithSifobiExcelStyles;
use App\Modules\Inventory\Models\UnitConversion;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class UnitConversionsExport implements FromCollection, ShouldAutoSize, WithEvents, WithHeadings, WithMapping
{
    use WithSifobiExcelStyles;

    public function __construct(private readonly int $tenantId)
    {
    }

    public function collection(): Collection
    {
        return UnitConversion::query()
            ->with(['item', 'fromUnit', 'toUnit'])
            ->where('tenant_id', $this->tenantId)
            ->orderBy('item_id')
            ->get();
    }

    public function headings(): array
    {
        return ['item_sku', 'item_name', 'from_unit', 'to_unit', 'factor'];
    }

    public function map(mixed $row): array
    {
        return [
            $row->item?->canonical_sku,
            $row->item?->name,
            $row->fromUnit?->code,
            $row->toUnit?->code,
            $row->factor ?? $row->multiply_rate,
        ];
    }
}

