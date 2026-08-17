<?php

namespace App\Support;

class AuditAttributeLabels
{
    private const LABELS = [
        'status' => 'Status',
        'review_status' => 'Status Review',
        'approval_status' => 'Status Approval',
        'name' => 'Nama',
        'email' => 'Email',
        'phone' => 'Telepon',
        'code' => 'Kode',
        'is_active' => 'Aktif',
        'outlet_id' => 'Outlet',
        'department_id' => 'Departemen',
        'supplier_id' => 'Supplier',
        'brand_id' => 'Brand',
        'item_id' => 'Item',
        'menu_id' => 'Menu',
        'purchase_order_id' => 'Purchase Order',
        'po_type' => 'Tipe PO',
        'needed_at' => 'Dibutuhkan Tanggal',
        'planned_submit_at' => 'Rencana Submit',
        'source' => 'Sumber',
        'receipt_date' => 'Tanggal Terima',
        'received_at' => 'Waktu Diterima',
        'version_number' => 'Versi Resep',
        'test_date' => 'Tanggal Uji Coba',
        'volume_production' => 'Volume Produksi',
        'approved_by' => 'Disetujui Oleh',
        'rejected_reason' => 'Alasan Ditolak',
        'type' => 'Tipe',
        'opname_date' => 'Tanggal Opname',
        'business_date' => 'Tanggal Bisnis',
        'shift' => 'Shift',
        'qty' => 'Jumlah',
        'reason_category' => 'Kategori Alasan',
        'reason_detail' => 'Detail Alasan',
        'approval_notes' => 'Catatan Approval',
        'from_outlet_id' => 'Outlet Asal',
        'to_outlet_id' => 'Outlet Tujuan',
        'transfer_date' => 'Tanggal Transfer',
        'mutation_type' => 'Tipe Mutasi',
        'stock_target' => 'Target Stok',
        'qty_change' => 'Perubahan Qty',
        'balance_after' => 'Saldo Setelah',
        'reference_type' => 'Tipe Referensi',
        'reference_id' => 'ID Referensi',
    ];

    public static function label(string $attribute): string
    {
        return self::LABELS[$attribute] ?? ucwords(str_replace('_', ' ', $attribute));
    }
}
