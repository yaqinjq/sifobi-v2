<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_count_lines', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('document_id');
            $table->unsignedBigInteger('item_id');
            // Snapshot dari inventory_count_template_items SAAT dokumen dibuat
            // -- supaya dokumen yang sudah ada tidak berubah kalau template
            // di-edit kemudian.
            $table->string('subcategory_label', 100)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            // Satu baris = 1 item di 1 lokasi. Item dengan 2 lokasi (mis.
            // Gudang + Bar Area) menghasilkan 2 baris, BUKAN 1 baris dengan
            // kolom ganda -- lebih mudah divalidasi per lokasi & konsisten
            // dengan cara is_counted bekerja per lokasi.
            $table->string('location', 32);
            $table->decimal('qty_whole', 18, 6)->nullable();
            $table->decimal('qty_loose', 18, 6)->nullable();
            $table->unsignedBigInteger('whole_unit_id')->nullable();
            $table->unsignedBigInteger('loose_unit_id')->nullable();
            // Snapshot item.inventory_ratio SAAT baris dibuat -- dipakai utk
            // menghitung qty_base DI LAPORAN (bukan ditulis balik sebagai
            // saldo). Null kalau requires_conversion true tapi item master
            // belum punya konfigurasi rasio yang valid (lihat config_valid).
            $table->decimal('conversion_factor', 18, 6)->nullable();
            $table->boolean('requires_conversion')->default(false);
            // false = item ini butuh konversi (requires_conversion) TAPI
            // item master belum punya inventory_unit/ratio yang valid saat
            // baris ini dibuat -- blokir status "siap dikirim" dokumen induk
            // selama baris ini begini, TAPI tetap boleh diisi sebagai draft
            // (angka mentah tersimpan, qty_base tidak ditampilkan seolah
            // pasti).
            $table->boolean('config_valid')->default(true);
            // Beda null (belum disentuh) vs is_counted=true dengan qty=0
            // (tombol "Stok Nol" / staff sengaja isi 0) -- JANGAN diisi dari
            // saldo sistem secara default.
            $table->boolean('is_counted')->default(false);
            $table->dateTime('counted_at')->nullable();
            $table->unsignedBigInteger('counted_by')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['document_id', 'item_id', 'location'], 'uq_inv_count_lines_doc_item_loc');
            $table->index(['tenant_id', 'document_id'], 'idx_inv_count_lines_doc');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_count_lines');
    }
};
