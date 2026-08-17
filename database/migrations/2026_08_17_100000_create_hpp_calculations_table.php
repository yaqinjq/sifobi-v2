<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hpp_calculations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained(indexName: 'fk_hpp_calc_tenant')->cascadeOnDelete();
            $table->foreignId('outlet_id')->nullable()->constrained(indexName: 'fk_hpp_calc_outlet')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained(table: 'users', indexName: 'fk_hpp_calc_created_by')->nullOnDelete();
            $table->string('product_name');
            $table->json('payload_json')->comment('Snapshot bahan baku + biaya produksi/overhead, dihitung di klien (kalkulator coba-coba, bukan resep resmi)');
            $table->decimal('ingredients_total', 19, 4)->default(0);
            $table->decimal('other_costs_total', 19, 4)->default(0);
            $table->decimal('total_cost', 19, 4)->default(0);
            $table->decimal('volume_production', 12, 4)->default(1);
            $table->decimal('hpp_per_unit', 19, 4)->default(0);
            $table->timestamps();

            $table->index(['tenant_id', 'created_by'], 'idx_hpp_calc_tenant_creator');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hpp_calculations');
    }
};
