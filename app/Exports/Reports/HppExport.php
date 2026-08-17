<?php

namespace App\Exports\Reports;

use App\Modules\Production\Models\Recipe;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class HppExport implements FromArray, ShouldAutoSize, WithEvents
{
    /**
     * Kolom: A=Menu B=Versi C=Item/Label D=Qty Resep(base) E=Qty Beli(base)
     * F=Harga Beli G=Biaya. Baris subtotal/HPP dicatat di sini supaya
     * AfterSheet bisa menimpa kolom G dengan rumus, bukan angka statis.
     *
     * @var array<int, array{type: string, ingredient_first_row?: int, ingredient_last_row?: int, other_first_row?: int, other_last_row?: int, ingredients_total_row?: int, other_total_row?: int, total_row?: int, volume_row?: int, hpp_row?: int}>
     */
    private array $recipeRowMeta = [];

    /**
     * @param  array<string, mixed>  $filters
     */
    public function __construct(
        private readonly int $tenantId,
        private readonly array $filters = []
    ) {
    }

    public function array(): array
    {
        $rows = [
            ['Laporan HPP', 'Export: '.now()->format('d M Y H:i')],
            ['Menu', 'Versi', 'Item / Biaya', 'Qty Resep (satuan dasar)', 'Qty Beli (satuan dasar)', 'Harga Beli', 'Biaya'],
        ];
        $rowNum = 3; // 1-indexed, baris ke-3 adalah baris data pertama

        foreach ($this->recipes() as $recipe) {
            $hpp = $recipe->hpp();
            $meta = ['ingredient_first_row' => $rowNum];

            foreach ($hpp['ingredient_rows'] as $row) {
                $ingredient = $row['ingredient'];
                $rows[] = [
                    $recipe->menu?->name,
                    'v'.$recipe->version_number,
                    $ingredient->item?->name,
                    (float) $ingredient->recipeQtyBase(),
                    (float) $ingredient->buyQtyBase(),
                    (float) $ingredient->buy_price,
                    (float) $row['cost'],
                ];
                $rowNum++;
            }
            $meta['ingredient_last_row'] = $rowNum - 1;

            $meta['ingredients_total_row'] = $rowNum;
            $rows[] = [$recipe->menu?->name, 'v'.$recipe->version_number, 'Total Bahan Baku', '', '', '', (float) $hpp['ingredients_total']];
            $rowNum++;

            $meta['other_first_row'] = $rowNum;
            foreach ($hpp['other_cost_rows'] as $cost) {
                $rows[] = [$recipe->menu?->name, 'v'.$recipe->version_number, $cost->label, '', '', '', (float) $cost->amount];
                $rowNum++;
            }
            $meta['other_last_row'] = $rowNum - 1;

            $meta['other_total_row'] = $rowNum;
            $rows[] = [$recipe->menu?->name, 'v'.$recipe->version_number, 'Total Biaya Lain', '', '', '', (float) $hpp['other_costs_total']];
            $rowNum++;

            $meta['total_row'] = $rowNum;
            $rows[] = [$recipe->menu?->name, 'v'.$recipe->version_number, 'TOTAL BIAYA (per batch)', '', '', '', (float) $hpp['total_cost']];
            $rowNum++;

            $meta['volume_row'] = $rowNum;
            $rows[] = [$recipe->menu?->name, 'v'.$recipe->version_number, 'Volume Produksi', '', '', '', (float) $hpp['volume_production']];
            $rowNum++;

            $meta['hpp_row'] = $rowNum;
            $rows[] = [$recipe->menu?->name, 'v'.$recipe->version_number, 'HPP per Unit', '', '', '', (float) $hpp['hpp_per_unit']];
            $rowNum++;

            $this->recipeRowMeta[] = $meta;
        }

        return $rows;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();
                $highestColumn = $sheet->getHighestColumn();
                $highestRow = $sheet->getHighestRow();

                $sheet->freezePane('A3');
                $sheet->getStyle("A1:{$highestColumn}1")->getFont()->setBold(true);
                $sheet->getStyle("A2:{$highestColumn}2")->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1B4332']],
                ]);

                // Ganti angka statis dengan rumus supaya finance bisa ubah
                // Harga Beli (F) langsung di Excel dan lihat Biaya (G) &
                // total-nya ikut ter-update.
                foreach ($this->recipeRowMeta as $meta) {
                    for ($r = $meta['ingredient_first_row']; $r <= $meta['ingredient_last_row']; $r++) {
                        $sheet->setCellValue("G{$r}", "=IF(E{$r}=0,0,D{$r}/E{$r}*F{$r})");
                    }

                    $sheet->setCellValue(
                        "G{$meta['ingredients_total_row']}",
                        "=SUM(G{$meta['ingredient_first_row']}:G{$meta['ingredient_last_row']})"
                    );

                    if ($meta['other_last_row'] >= $meta['other_first_row']) {
                        $sheet->setCellValue(
                            "G{$meta['other_total_row']}",
                            "=SUM(G{$meta['other_first_row']}:G{$meta['other_last_row']})"
                        );
                    }

                    $sheet->setCellValue(
                        "G{$meta['total_row']}",
                        "=G{$meta['ingredients_total_row']}+G{$meta['other_total_row']}"
                    );

                    $sheet->setCellValue(
                        "G{$meta['hpp_row']}",
                        "=IF(G{$meta['volume_row']}=0,0,G{$meta['total_row']}/G{$meta['volume_row']})"
                    );

                    $sheet->getStyle("A{$meta['total_row']}:G{$meta['total_row']}")->getFont()->setBold(true);
                    $sheet->getStyle("A{$meta['hpp_row']}:G{$meta['hpp_row']}")->applyFromArray([
                        'font' => ['bold' => true],
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E8F5E9']],
                    ]);
                }

                if ($highestRow >= 3) {
                    $sheet->getStyle("G3:G{$highestRow}")->getNumberFormat()->setFormatCode('#,##0');
                }
            },
        ];
    }

    /**
     * @return Collection<int, Recipe>
     */
    private function recipes(): Collection
    {
        return Recipe::query()
            ->where('tenant_id', $this->tenantId)
            ->where('status', Recipe::STATUS_APPROVED)
            ->with(['menu', 'ingredients.item', 'otherCosts'])
            ->when($this->filters['menu_id'] ?? null, fn ($q, $menuId) => $q->where('menu_id', $menuId))
            ->when($this->filters['brand_id'] ?? null, fn ($q, $brandId) => $q->whereHas('menu', fn ($mq) => $mq->where('brand_id', $brandId)))
            ->orderByDesc('approved_at')
            ->get();
    }
}
