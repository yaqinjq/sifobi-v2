<?php

namespace App\Exports\Templates;

use App\Exports\Concerns\WithSifobiExcelStyles;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;

class UnitConversionsImportTemplate implements FromArray, ShouldAutoSize, WithEvents, WithTitle
{
    use WithSifobiExcelStyles;

    public function array(): array
    {
        return [
            ['item_sku', 'from_unit', 'to_unit', 'factor'],
            ['BCF-001', 'sachet', 'gr', '500'],
        ];
    }

    public function title(): string
    {
        return 'CONVERSIONS';
    }
}

