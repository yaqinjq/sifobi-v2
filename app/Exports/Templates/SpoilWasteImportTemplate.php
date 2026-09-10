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

class SpoilWasteImportTemplate implements WithMultipleSheets
{
    public function sheets(): array
    {
        return [
            new SpoilWasteTemplateSheet(),
            new SpoilWasteInstructionSheet(),
        ];
    }
}

class SpoilWasteTemplateSheet implements FromArray, ShouldAutoSize, WithEvents, WithStyles, WithTitle
{
    use WithSifobiExcelStyles;

    public function array(): array
    {
        return [
            ['outlet', 'departemen', 'sku', 'unit', 'qty', 'tanggal_kejadian', 'kategori_alasan', 'keterangan'],
            ['MKO Outlet 1', 'Kitchen', 'BCF-001', 'gr', '500', '2026-07-03', 'Kadaluarsa', 'Contoh baris — hapus sebelum upload'],
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            2 => [
                'font' => ['italic' => true, 'color' => ['rgb' => '6B7280']],
            ],
        ];
    }

    public function title(): string
    {
        return 'SPOIL_WASTE';
    }
}

class SpoilWasteInstructionSheet implements FromArray, ShouldAutoSize, WithStyles, WithTitle
{
    public function array(): array
    {
        return [
            ['Kolom', 'Keterangan', 'Contoh', 'Wajib?'],
            ['outlet', 'Nama atau kode outlet, harus sudah terdaftar di sistem', 'MKO Outlet 1', 'Ya'],
            ['departemen', 'Nama atau kode departemen (opsional, kosongkan jika tidak spesifik)', 'Kitchen', 'Tidak'],
            ['sku', 'SKU item persis seperti di Master Data Item', 'BCF-001', 'Ya'],
            ['unit', 'Kode/singkatan satuan, mis. gr/kg/pcs', 'gr', 'Ya'],
            ['qty', 'Jumlah yang di-spoil/waste, dalam satuan kolom unit', '500', 'Ya'],
            ['tanggal_kejadian', 'Tanggal kejadian sebenarnya (boleh tanggal lampau)', '2026-07-03', 'Ya'],
            ['kategori_alasan', 'Salah satu: Kadaluarsa, Rusak/Cacat, Kesalahan Produksi, Tumpah, Reject Kualitas, Lainnya', 'Kadaluarsa', 'Ya'],
            ['keterangan', 'Catatan tambahan bebas', 'Ditemukan saat opname', 'Tidak'],
            [],
            ['CATATAN PENTING:'],
            ['1. Qty akan langsung memotong stok SAAT INI (bukan stok di tanggal historisnya) — pastikan stok saat ini cukup, atau import berurutan dari yang paling lama.'],
            ['2. Baris yang berhasil otomatis berstatus Approved (tidak perlu approve manual satu-satu).'],
            ['3. Baris yang gagal (outlet/item/satuan tidak ditemukan, dll.) akan dilewati dan dilaporkan errornya — baris lain tetap diproses.'],
            ['4. Foto tidak bisa diisi lewat import — bisa ditambahkan manual lewat halaman detail setelah import jika diperlukan.'],
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
