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

class DepartmentCategoryMappingTemplate implements WithMultipleSheets
{
    public function sheets(): array
    {
        return [
            new DepartmentCategoryMappingTemplateSheet(),
            new DepartmentCategoryMappingInstructionSheet(),
        ];
    }
}

class DepartmentCategoryMappingTemplateSheet implements FromArray, ShouldAutoSize, WithEvents, WithStyles, WithTitle
{
    use WithSifobiExcelStyles;

    public function array(): array
    {
        return [
            ['departemen', 'kategori', 'urutan', 'sub_kategori'],
            ['BAR', 'BAR DRY GOODS', '1', 'COFFEE & TEA'],
            ['BAR', 'BAR DRY GOODS', '2', 'MILK'],
            ['BAR', 'BAR DRY GOODS', '3', 'FRUIT & VEGETABLES'],
            ['KITCHEN', 'KITCHEN WIP', '1', 'Contoh sub kategori kitchen — hapus sebelum upload'],
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            5 => [
                'font' => ['italic' => true, 'color' => ['rgb' => '6B7280']],
            ],
        ];
    }

    public function title(): string
    {
        return 'MAPPING_OPNAME';
    }
}

class DepartmentCategoryMappingInstructionSheet implements FromArray, ShouldAutoSize, WithStyles, WithTitle
{
    public function array(): array
    {
        return [
            ['Kolom', 'Keterangan', 'Contoh', 'Wajib?'],
            ['departemen', 'Nama atau kode departemen, harus sudah terdaftar di sistem', 'BAR', 'Ya'],
            ['kategori', 'Nama kategori utama (akan dibuat otomatis jika belum ada)', 'BAR DRY GOODS', 'Ya'],
            ['urutan', 'Nomor urut sub kategori dalam kategori ini (menentukan urutan tampil "Sesuai Form")', '1', 'Tidak'],
            ['sub_kategori', 'Nama sub kategori di dalam kategori tsb (akan dibuat otomatis jika belum ada)', 'COFFEE & TEA', 'Tidak'],
            [],
            ['CATATAN PENTING:'],
            ['1. Satu baris = satu pasangan Kategori + Sub Kategori untuk satu Departemen. Ulangi baris "kategori" yang sama untuk tiap sub kategorinya.'],
            ['2. Kategori dan Sub Kategori yang belum ada di Master Data akan dibuat otomatis mengikuti file ini.'],
            ['3. Import ulang file yang sama aman dilakukan (upsert) — urutan akan diperbarui, tidak membuat data duplikat.'],
            ['4. Kategori yang di-mapping ke suatu departemen akan otomatis muncul sebagai pilihan filter Kategori di halaman Opname departemen tsb.'],
            ['5. Assign item ke Sub Kategori tetap lewat Master Data Item seperti biasa — file ini hanya mengatur struktur & urutan kategori, bukan daftar item.'],
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1B4332']],
            ],
        ];
    }

    public function title(): string
    {
        return 'PETUNJUK';
    }
}
