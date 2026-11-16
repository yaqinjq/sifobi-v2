<?php

namespace App\Exports\Templates;

use App\Support\Excel\OpnameGridWriter;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;

/**
 * Template Excel untuk isi massal 1 sesi Opname (status DRAFT) -- baris =
 * OpnameItem yang SUDAH ADA di sesi ini (tidak pernah membuat baris baru),
 * urutan sama persis dengan mode "Sesuai Form" di show.blade.php
 * (Item::formSortKey() per department_id BARIS -- bukan department_id
 * sesi -- supaya item yang shared antar-departemen tetap terurut benar).
 *
 * Layout persis form kertas fisik (lihat OpnameGridWriter) -- header 2-baris
 * bermerge, kategori sebagai baris pemisah, kolom SET OF (rasio konversi) &
 * GUDANG (saldo warehouse LIVE, referensi saja) ikut ditampilkan, kolom
 * Utuh/Ecer dikosongkan untuk diisi staf, Total & TOTAL Per Satuan berupa
 * rumus Excel asli (bukan angka statis).
 */
class OpnameSessionFillTemplate implements FromArray, WithEvents, WithTitle
{
    /**
     * @param  Collection<int, \App\Modules\Operations\Models\OpnameItem>  $opnameItems
     * @param  array<int, string>  $gudangBalances  item_id => qty_on_hand (WAREHOUSE)
     */
    public function __construct(
        private readonly Collection $opnameItems,
        private readonly array $gudangBalances,
        private readonly string $areaLabel,
    ) {
    }

    public function array(): array
    {
        return [[]];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();

                $sorted = $this->opnameItems->sortBy(
                    fn ($opnameItem) => $opnameItem->item?->formSortKey($opnameItem->department_id) ?? ''
                );

                $rows = [];

                foreach ($sorted as $opnameItem) {
                    $item = $opnameItem->item;

                    $rows[] = [
                        'category' => $item?->categoryForDepartment($opnameItem->department_id)?->name ?: 'Lainnya',
                        'kode_item' => $item?->canonical_sku ?? '-',
                        'item_name' => $item?->name ?? '-',
                        'satuan' => $item?->inventoryUnit?->abbreviation ?: $item?->baseUnit?->abbreviation ?: '-',
                        'set_of_unit' => $item?->baseUnit?->abbreviation,
                        'set_of_qty' => $item ? (string) $item->inventory_ratio : null,
                        'gudang_qty' => $this->gudangBalances[$opnameItem->item_id] ?? 0,
                        'area_utuh' => null,
                        'area_ecer' => null,
                        'department_label' => $opnameItem->department?->name ?? $item?->primaryDepartment?->name ?? '-',
                    ];
                }

                $firstDataRow = OpnameGridWriter::writeHeader($sheet, 1, $this->areaLabel);
                $sheet->freezePane('A'.$firstDataRow);
                OpnameGridWriter::writeRows($sheet, $firstDataRow, $rows);

                foreach (range('A', 'M') as $col) {
                    $sheet->getColumnDimension($col)->setAutoSize(true);
                }
            },
        ];
    }

    public function title(): string
    {
        return 'ISI_OPNAME';
    }
}
