<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_count_document_revisions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('document_id');
            $table->string('from_status', 16);
            $table->string('to_status', 16);
            // Alasan WAJIB diisi saat SUBMITTED -> DRAFT (koreksi berizin)
            // supaya ada jejak audit "kenapa" -- bukan overwrite diam-diam.
            $table->text('reason')->nullable();
            $table->unsignedBigInteger('performed_by');
            $table->dateTime('performed_at');
            $table->timestamps();

            $table->index(['tenant_id', 'document_id'], 'idx_inv_count_doc_revisions_doc');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_count_document_revisions');
    }
};
