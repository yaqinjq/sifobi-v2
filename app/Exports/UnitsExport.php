<?php

namespace App\Exports;

use App\Exports\Concerns\WithSifobiExcelStyles;
use App\Modules\Inventory\Models\Unit;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class UnitsExport implements FromCollection, ShouldAutoSize, WithEvents, WithHeadings, WithMapping
{
    use WithSifobiExcelStyles;

    public function __construct(private readonly int $tenantId)
    {
    }

    public function collection(): Collection
    {
        return Unit::query()
            ->where('tenant_id', $this->tenantId)
            ->orderBy('code')
            ->get();
    }

    public function headings(): array
    {
        return ['code', 'name', 'abbreviation'];
    }

    public function map(mixed $row): array
    {
        return [$row->code, $row->name, $row->abbreviation];
    }
}

