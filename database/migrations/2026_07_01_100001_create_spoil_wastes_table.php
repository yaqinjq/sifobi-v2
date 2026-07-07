<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('spoil_wastes')) {
            Schema::create('spoil_wastes', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('tenant_id');
                $table->unsignedBigInteger('outlet_id');
                $table->unsignedBigInteger('department_id')->nullable();
                $table->unsignedBigInteger('item_id');
                $table->unsignedBigInteger('unit_id');
                $table->decimal('qty', 18, 6);
                $table->decimal('qty_in_base_unit', 18, 6)->default(0);
                $table->string('reason_category', 48)->default('LAINNYA');
                $table->text('reason_detail')->nullable();
                $table->string('photo')->nullable();
                $table->string('photo_path')->nullable();
                $table->string('photo_hash', 128)->nullable();
                $table->json('photo_meta')->nullable();
                $table->boolean('is_duplicate_photo')->default(false);
                $table->unsignedBigInteger('duplicate_ref_id')->nullable();
                $table->date('recorded_date');
                $table->dateTime('recorded_at');
                $table->string('status', 32)->default('PENDING');
                $table->string('approval_status', 32)->default('PENDING');
                $table->unsignedBigInteger('approved_by')->nullable();
                $table->dateTime('approved_at')->nullable();
                $table->text('approval_notes')->nullable();
                $table->unsignedBigInteger('mutation_id')->nullable();
                $table->unsignedBigInteger('created_by');
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['tenant_id', 'outlet_id', 'recorded_date'], 'idx_spoil_tenant_outlet_date');
                $table->index(['tenant_id', 'photo_hash'], 'idx_spoil_tenant_photo_hash');
            });

            return;
        }

        $columns = [
            'qty_in_base_unit' => fn (Blueprint $table) => $table->decimal('qty_in_base_unit', 18, 6)->default(0),
            'reason_category' => fn (Blueprint $table) => $table->string('reason_category', 48)->default('LAINNYA'),
            'reason_detail' => fn (Blueprint $table) => $table->text('reason_detail')->nullable(),
            'photo' => fn (Blueprint $table) => $table->string('photo')->nullable(),
            'photo_meta' => fn (Blueprint $table) => $table->json('photo_meta')->nullable(),
            'is_duplicate_photo' => fn (Blueprint $table) => $table->boolean('is_duplicate_photo')->default(false),
            'duplicate_ref_id' => fn (Blueprint $table) => $table->unsignedBigInteger('duplicate_ref_id')->nullable(),
            'recorded_date' => fn (Blueprint $table) => $table->date('recorded_date')->nullable(),
            'status' => fn (Blueprint $table) => $table->string('status', 32)->default('PENDING'),
            'approval_notes' => fn (Blueprint $table) => $table->text('approval_notes')->nullable(),
            'created_by' => fn (Blueprint $table) => $table->unsignedBigInteger('created_by')->nullable(),
        ];

        foreach ($columns as $column => $definition) {
            if (! Schema::hasColumn('spoil_wastes', $column)) {
                Schema::table('spoil_wastes', fn (Blueprint $table) => $definition($table));
            }
        }

        Schema::table('spoil_wastes', function (Blueprint $table): void {
            $table->index(['tenant_id', 'outlet_id', 'recorded_date'], 'idx_spoil_tenant_outlet_date');
            $table->index(['tenant_id', 'photo_hash'], 'idx_spoil_tenant_photo_hash');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('spoil_wastes')) {
            return;
        }

        Schema::table('spoil_wastes', function (Blueprint $table): void {
            foreach ([
                'qty_in_base_unit',
                'reason_category',
                'reason_detail',
                'photo',
                'photo_meta',
                'is_duplicate_photo',
                'duplicate_ref_id',
                'recorded_date',
                'status',
                'approval_notes',
                'created_by',
            ] as $column) {
                if (Schema::hasColumn('spoil_wastes', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
