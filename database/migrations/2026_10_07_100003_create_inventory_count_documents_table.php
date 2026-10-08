<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_count_documents', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('outlet_id');
            $table->unsignedBigInteger('department_id');
            $table->unsignedBigInteger('template_id');
            $table->date('business_date');
            // DRAFT -> SUBMITTED saja untuk MVP ini. SUBMITTED BUKAN approval
            // atau posting stok -- cuma berarti hasil hitung sudah dikirim.
            // Koreksi sesudah SUBMITTED HARUS lewat requestRevision() yang
            // tercatat di inventory_count_document_revisions, bukan
            // overwrite langsung.
            $table->string('status', 16)->default('DRAFT');
            // Token optimistic concurrency -- dicek di setiap update supaya
            // 2 tab/perangkat yang edit draft yang sama tidak saling timpa
            // diam-diam.
            $table->unsignedInteger('version')->default(1);
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('submitted_by')->nullable();
            $table->dateTime('submitted_at')->nullable();
            $table->unsignedBigInteger('last_saved_by')->nullable();
            $table->dateTime('last_saved_at')->nullable();
            $table->timestamps();

            // Mencegah draft ganda akibat double-submit/double-klik "Mulai
            // Isi" untuk scope+tanggal yang sama -- 1 dokumen per
            // tenant+outlet+departemen+template+tanggal. Revisi dilakukan di
            // dokumen yang SAMA (balik status ke DRAFT), bukan baris baru.
            $table->unique(
                ['tenant_id', 'outlet_id', 'department_id', 'template_id', 'business_date'],
                'uq_inv_count_docs_scope_date'
            );
            $table->index(['tenant_id', 'outlet_id', 'department_id', 'status'], 'idx_inv_count_docs_scope_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_count_documents');
    }
};
