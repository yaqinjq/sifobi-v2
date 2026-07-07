<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('open_stocks', function (Blueprint $table) {
            $table->foreignId('voided_by')
                ->nullable()
                ->after('mutation_id')
                ->constrained(table: 'users', indexName: 'fk_open_stocks_voided_by')
                ->nullOnDelete();
            $table->timestamp('voided_at')->nullable()->after('voided_by');
            $table->text('void_reason')->nullable()->after('voided_at');
        });
    }

    public function down(): void
    {
        Schema::table('open_stocks', function (Blueprint $table) {
            if (Schema::getConnection()->getDriverName() !== 'sqlite') {
                $table->dropForeign('fk_open_stocks_voided_by');
            }
            $table->dropColumn(['voided_by', 'voided_at', 'void_reason']);
        });
    }
};
