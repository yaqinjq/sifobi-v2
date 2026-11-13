<?php

namespace App\Exports\Templates;

use App\Exports\Concerns\WithSifobiExcelStyles;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Template Excel untuk isi massal 1 sesi Opname (status DRAFT) -- baris =
 * OpnameItem yang SUDAH ADA di sesi ini (tidak pernah membuat baris baru),
 * urutan sama persis dengan mode "Sesuai Form" di show.blade.php
 * (Item::formSortKey() per department_id BARIS -- bukan department_id
 * sesi -- supaya item yang shared antar-departemen tetap terurut benar).
 */
class OpnameSessionFillTemplate implements FromArray, ShouldAutoSize, WithEvents, WithStyles, WithTitle
{
    use WithSifobiExcelStyles;

    /**
     * @param  Collection<int, \App\Modules\Operations\Models\OpnameItem>  $opnameItems
     */
    public function __construct(private readonly Collection $opnameItems)
    {
    }

    public function array(): array
    {
        $sorted = $this->opnameItems->sortBy(
            fn ($opnameItem) => $opnameItem->item?->formSortKey($opnameItem->department_id) ?? ''
        );

        $rows = [
            ['Kategori', 'Item', 'SKU', 'Departemen', 'Satuan Utuh', 'Satuan Ecer', 'Fisik Utuh', 'Fisik Ecer'],
        ];

        foreach ($sorted as $opnameItem) {
            $item = $opnameItem->item;

            $rows[] = [
                $item?->categoryForDepartment($opnameItem->department_id)?->name ?: 'Lainnya',
                $item?->name ?? '-',
                $item?->canonical_sku ?? '-',
                $opnameItem->department?->name ?? $item?->primaryDepartment?->name ?? '-',
                $item?->inventoryUnit?->abbreviation ?: '-',
                $item?->baseUnit?->abbreviation ?: '-',
                '',
                '',
            ];
        }

        return $rows;
    }

    public function styles(Worksheet $sheet): array
    {
        return [];
    }

    public function title(): string
    {
        return 'ISI_OPNAME';
    }
}
