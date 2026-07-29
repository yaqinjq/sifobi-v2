<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table): void {
            $table->foreignId('supplier_id')->nullable()->after('department_id')
                ->constrained('suppliers')->nullOnDelete();
            $table->foreignId('integration_profile_id')->nullable()->after('supplier_id')
                ->constrained('integration_profiles')->nullOnDelete();
            $table->foreignId('submitted_by')->nullable()->after('requested_by')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable()->after('submitted_by');
            $table->foreignId('rejected_by')->nullable()->after('approved_at')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable()->after('rejected_by');
            $table->text('rejection_notes')->nullable()->after('rejected_at');
            $table->foreignId('sent_by')->nullable()->after('rejection_notes')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('sent_at')->nullable()->after('sent_by');
            $table->foreignId('closed_by')->nullable()->after('sent_at')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable()->after('closed_by');
        });

        // Tambah purchase_order_id ke goods_receipts agar GR bisa referensi PO
        Schema::table('goods_receipts', function (Blueprint $table): void {
            $table->foreignId('purchase_order_id')->nullable()->after('id')
                ->constrained('purchase_orders')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('goods_receipts', function (Blueprint $table): void {
            $table->dropForeign(['purchase_order_id']);
            $table->dropColumn('purchase_order_id');
        });

        Schema::table('purchase_orders', function (Blueprint $table): void {
            $table->dropForeign(['supplier_id']);
            $table->dropForeign(['integration_profile_id']);
            $table->dropForeign(['submitted_by']);
            $table->dropForeign(['rejected_by']);
            $table->dropForeign(['sent_by']);
            $table->dropForeign(['closed_by']);
            $table->dropColumn([
                'supplier_id', 'integration_profile_id',
                'submitted_by', 'submitted_at',
                'rejected_by', 'rejected_at', 'rejection_notes',
                'sent_by', 'sent_at',
                'closed_by', 'closed_at',
            ]);
        });
    }
};
