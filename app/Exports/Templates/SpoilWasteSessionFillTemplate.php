<?php

namespace App\Exports\Templates;

use App\Support\Excel\SpoilGridWriter;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;

/**
 * Template Excel "Isi Massal" Spoil & Waste untuk 1 outlet+departemen --
 * padanan OpnameSessionFillTemplate, layout persis form kertas "SPOIL BAR"
 * (lihat SpoilGridWriter): TGL per baris (bukan terkunci 1 tanggal, beda
 * dari Opname yang snapshot 1 hari -- spoil memang lintas-tanggal dalam 1
 * lembar yang sama), Kode Item/ITEM/Satuan pre-filled, STOK dikosongkan
 * untuk diisi staf, PRICE/UNIT pre-filled dari harga standar/terakhir,
 * TOTAL HARGA berupa rumus Excel asli.
 */
class SpoilWasteSessionFillTemplate implements FromArray, WithEvents, WithTitle
{
    /**
     * @param  Collection<int, \App\Modules\Inventory\Models\Item>  $items
     */
    public function __construct(
        private readonly Collection $items,
        private readonly ?int $departmentId,
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

                $sorted = $this->items->sortBy(
                    fn ($item) => $item->formSortKey($this->departmentId)
                );

                $rows = [];

                foreach ($sorted as $item) {
                    $rows[] = [
                        'category' => $item->categoryForDepartment($this->departmentId)?->name ?: 'Lainnya',
                        'kode_item' => $item->canonical_sku ?? '-',
                        'item_name' => $item->name,
                        'satuan' => $item->inventoryUnit?->abbreviation ?: $item->baseUnit?->abbreviation ?: '-',
                        'tgl' => null,
                        'stok' => null,
                        'price_per_unit' => $item->standard_cost ?: $item->last_purchase_price ?: 0,
                        'reason_category' => null,
                        'note' => null,
                    ];
                }

                $firstDataRow = SpoilGridWriter::writeHeader($sheet, 1);
                $sheet->freezePane('A'.$firstDataRow);
                SpoilGridWriter::writeRows($sheet, $firstDataRow, $rows);

                foreach (range('A', 'I') as $col) {
                    $sheet->getColumnDimension($col)->setAutoSize(true);
                }
            },
        ];
    }

    public function title(): string
    {
        return 'ISI_SPOIL';
    }
}
