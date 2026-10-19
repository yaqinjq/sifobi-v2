<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_count_templates', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            // null = template generik, bisa dipakai departemen mana pun yang
            // belum punya template spesifik -- lihat InventoryCountTemplate.
            $table->unsignedBigInteger('department_id')->nullable();
            $table->string('code', 64);
            $table->string('name', 150);
            $table->string('frequency', 16)->default('DAILY');
            // Daftar lokasi yang dikenal template ini, mis. ["GUDANG","BAR_AREA"].
            // Item di dalam template boleh cuma pakai sebagian lokasi ini
            // (lihat inventory_count_template_items.locations).
            $table->json('locations');
            $table->boolean('is_generic')->default(false);
            // is_configured = false berarti template ini belum lolos verifikasi
            // mapping item/lokasi -- jangan ditawarkan sebagai pilihan pilot ke
            // staff sampai di-set true secara eksplisit lewat Settings.
            $table->boolean('is_configured')->default(false);
            $table->string('status', 16)->default('ACTIVE');
            $table->timestamps();

            $table->unique(['tenant_id', 'code'], 'uq_inv_count_templates_code');
            $table->index(['tenant_id', 'department_id'], 'idx_inv_count_templates_dept');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_count_templates');
    }
};
