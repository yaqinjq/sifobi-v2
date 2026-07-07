<?php

namespace App\Exports\Templates;

use App\Exports\Concerns\WithSifobiExcelStyles;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;

class UnitsImportTemplate implements FromArray, ShouldAutoSize, WithEvents, WithTitle
{
    use WithSifobiExcelStyles;

    public function array(): array
    {
        return [
            ['code', 'name', 'abbreviation'],
            ['GR', 'Gram', 'gr'],
        ];
    }

    public function title(): string
    {
        return 'UNITS';
    }
}

