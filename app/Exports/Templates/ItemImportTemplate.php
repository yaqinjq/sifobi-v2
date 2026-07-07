<?php

namespace App\Exports\Templates;

use App\Exports\Concerns\WithSifobiExcelStyles;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ItemImportTemplate implements WithMultipleSheets
{
    public function sheets(): array
    {
        return [
            new ItemTemplateSheet(),
            new ItemInstructionSheet(),
        ];
    }
}

class ItemTemplateSheet implements FromArray, ShouldAutoSize, WithEvents, WithStyles, WithTitle
{
    use WithSifobiExcelStyles;

    public function array(): array
    {
        return [
            [
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
            ],
            [
                'BCF-001',
                'Ajinomoto MSG 500g',
                'Bumbu penyedap',
                'Bumbu',
                'BAHAN_BAKU',
                'gr',
                'sachet',
                'karton',
                '500',
                '12000',
                '100',
                '25000',
                '1',
            ],
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            2 => [
                'font' => [
                    'italic' => true,
                    'color' => ['rgb' => '6B7280'],
                ],
            ],
        ];
    }

    public function title(): string
    {
        return 'ITEMS';
    }
}

class ItemInstructionSheet implements FromArray, ShouldAutoSize, WithStyles, WithTitle
{
    public function array(): array
    {
        return [
            ['Kolom', 'Keterangan', 'Contoh', 'Wajib?'],
            ['canonical_sku', 'Kode unik item, tidak boleh duplikat per tenant', 'BCF-001', 'Ya'],
            ['name', 'Nama lengkap item', 'Ajinomoto MSG 500g', 'Ya'],
            ['description', 'Deskripsi singkat item', 'Bumbu penyedap', 'Tidak'],
            ['item_category', 'Nama kategori item, dibuat otomatis jika belum ada', 'Bumbu', 'Tidak'],
            ['item_type', 'Pilih: BAHAN_BAKU, WIP_L1, WIP_L2, WIP_L3, PACKAGING, MENU_ITEM', 'BAHAN_BAKU', 'Ya'],
            ['base_unit', 'Kode/abbreviation satuan dasar', 'gr', 'Ya'],
            ['inventory_unit', 'Kode/abbreviation satuan inventory', 'sachet', 'Tidak'],
            ['purchase_unit', 'Kode/abbreviation satuan beli', 'karton', 'Tidak'],
            ['inventory_ratio', '1 inventory_unit = N base_unit', '500', 'Ya'],
            ['purchase_ratio', '1 purchase_unit = N base_unit', '12000', 'Ya'],
            ['yield_pct', 'Persentase yield 0-100', '100', 'Tidak'],
            ['last_purchase_price', 'Harga beli terakhir', '25000', 'Tidak'],
            ['is_active', '1 = aktif, 0 = non-aktif', '1', 'Ya'],
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => [
                    'bold' => true,
                    'color' => ['rgb' => 'FFFFFF'],
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '1B4332'],
                ],
            ],
        ];
    }

    public function title(): string
    {
        return 'PETUNJUK';
    }
}
