<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('item_stock_configs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('outlet_id')->constrained()->cascadeOnDelete();
            $table->decimal('min_stock_qty', 12, 4)->default(0);
            $table->decimal('max_stock_qty', 12, 4)->default(0);
            $table->decimal('reorder_point', 12, 4)->default(0);
            $table->foreignId('unit_id')->nullable()->constrained('units')->nullOnDelete();
            $table->unsignedInteger('avg_daily_usage_days')->default(7);
            $table->timestamps();

            $table->unique(['tenant_id', 'item_id', 'outlet_id'], 'uq_item_stock_configs_scope');
            $table->index(['tenant_id', 'outlet_id'], 'idx_item_stock_configs_outlet');
        });

        Schema::create('calendar_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('outlet_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('brand_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name', 255);
            $table->date('event_date');
            $table->date('event_end_date')->nullable();
            $table->enum('event_type', [
                'HARI_RAYA',
                'PROMO',
                'LIBURAN',
                'PEAK_SEASON',
                'CUSTOM',
            ]);
            $table->decimal('demand_multiplier', 5, 2)->default(1.00);
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'event_date'], 'idx_calendar_events_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_events');
        Schema::dropIfExists('item_stock_configs');
    }
};
