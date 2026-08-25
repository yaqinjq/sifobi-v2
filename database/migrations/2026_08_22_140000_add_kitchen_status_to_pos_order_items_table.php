<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pos_order_items', function (Blueprint $table): void {
            $table->string('status', 16)->default('PENDING')->after('sort_order');
            $table->timestamp('prepared_at')->nullable()->after('status');
            $table->foreignId('prepared_by')->nullable()->after('prepared_at')
                ->constrained('users', indexName: 'fk_pos_order_items_prepared_by')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('pos_order_items', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('prepared_by');
            $table->dropColumn(['status', 'prepared_at']);
        });
    }
};
