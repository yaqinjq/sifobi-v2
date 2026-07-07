<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('opname_sessions')) {
            Schema::create('opname_sessions', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('tenant_id');
                $table->unsignedBigInteger('outlet_id');
                $table->unsignedBigInteger('department_id')->nullable();
                $table->string('type', 24)->default('DAILY');
                $table->string('opname_type', 24)->default('DAILY');
                $table->date('opname_date');
                $table->date('business_date');
                $table->string('shift', 16)->nullable();
                $table->string('status', 32)->default('DRAFT');
                $table->text('notes')->nullable();
                $table->unsignedBigInteger('created_by');
                $table->unsignedBigInteger('started_by')->nullable();
                $table->unsignedBigInteger('submitted_by')->nullable();
                $table->unsignedBigInteger('approved_by')->nullable();
                $table->dateTime('submitted_at')->nullable();
                $table->dateTime('approved_at')->nullable();
                $table->unsignedBigInteger('posted_by')->nullable();
                $table->dateTime('posted_at')->nullable();
                $table->timestamps();

                $table->index(['tenant_id', 'outlet_id', 'opname_date'], 'idx_opname_tenant_outlet_date');
            });
        } else {
            $this->upgradeSessions();
        }

        if (! Schema::hasTable('opname_items')) {
            Schema::create('opname_items', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('tenant_id');
                $table->unsignedBigInteger('opname_session_id');
                $table->unsignedBigInteger('item_id');
                $table->unsignedBigInteger('unit_id');
                $table->unsignedBigInteger('department_id')->nullable();
                $table->decimal('system_qty', 18, 6)->default(0);
                $table->decimal('system_qty_base', 18, 6)->default(0);
                $table->decimal('counted_qty', 18, 6)->default(0);
                $table->decimal('physical_qty_whole', 18, 6)->default(0);
                $table->decimal('physical_qty_loose', 18, 6)->default(0);
                $table->decimal('physical_qty_base', 18, 6)->default(0);
                $table->decimal('variance_qty', 18, 6)->default(0);
                $table->decimal('variance', 18, 6)->default(0);
                $table->decimal('variance_value', 19, 4)->default(0);
                $table->boolean('is_counted')->default(false);
                $table->unsignedBigInteger('mutation_id')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index('opname_session_id', 'idx_opname_items_session');
                $table->unique(['tenant_id', 'opname_session_id', 'item_id'], 'uq_opname_items_scope');
            });

            return;
        }

        $this->upgradeItems();
    }

    public function down(): void
    {
        if (Schema::hasTable('opname_items')) {
            Schema::table('opname_items', function (Blueprint $table): void {
                foreach ([
                    'department_id',
                    'system_qty_base',
                    'physical_qty_whole',
                    'physical_qty_loose',
                    'physical_qty_base',
                    'variance',
                    'variance_value',
                    'is_counted',
                ] as $column) {
                    if (Schema::hasColumn('opname_items', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('opname_sessions')) {
            Schema::table('opname_sessions', function (Blueprint $table): void {
                foreach ([
                    'type',
                    'opname_date',
                    'shift',
                    'created_by',
                    'submitted_by',
                    'approved_by',
                    'submitted_at',
                    'approved_at',
                ] as $column) {
                    if (Schema::hasColumn('opname_sessions', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }

    private function upgradeSessions(): void
    {
        $columns = [
            'type' => fn (Blueprint $table) => $table->string('type', 24)->default('DAILY'),
            'opname_date' => fn (Blueprint $table) => $table->date('opname_date')->nullable(),
            'shift' => fn (Blueprint $table) => $table->string('shift', 16)->nullable(),
            'created_by' => fn (Blueprint $table) => $table->unsignedBigInteger('created_by')->nullable(),
            'submitted_by' => fn (Blueprint $table) => $table->unsignedBigInteger('submitted_by')->nullable(),
            'approved_by' => fn (Blueprint $table) => $table->unsignedBigInteger('approved_by')->nullable(),
            'submitted_at' => fn (Blueprint $table) => $table->dateTime('submitted_at')->nullable(),
            'approved_at' => fn (Blueprint $table) => $table->dateTime('approved_at')->nullable(),
        ];

        foreach ($columns as $column => $definition) {
            if (! Schema::hasColumn('opname_sessions', $column)) {
                Schema::table('opname_sessions', fn (Blueprint $table) => $definition($table));
            }
        }

        Schema::table('opname_sessions', function (Blueprint $table): void {
            $table->index(['tenant_id', 'outlet_id', 'opname_date'], 'idx_opname_tenant_outlet_date');
        });
    }

    private function upgradeItems(): void
    {
        $columns = [
            'department_id' => fn (Blueprint $table) => $table->unsignedBigInteger('department_id')->nullable(),
            'system_qty_base' => fn (Blueprint $table) => $table->decimal('system_qty_base', 18, 6)->default(0),
            'physical_qty_whole' => fn (Blueprint $table) => $table->decimal('physical_qty_whole', 18, 6)->default(0),
            'physical_qty_loose' => fn (Blueprint $table) => $table->decimal('physical_qty_loose', 18, 6)->default(0),
            'physical_qty_base' => fn (Blueprint $table) => $table->decimal('physical_qty_base', 18, 6)->default(0),
            'variance' => fn (Blueprint $table) => $table->decimal('variance', 18, 6)->default(0),
            'variance_value' => fn (Blueprint $table) => $table->decimal('variance_value', 19, 4)->default(0),
            'is_counted' => fn (Blueprint $table) => $table->boolean('is_counted')->default(false),
        ];

        foreach ($columns as $column => $definition) {
            if (! Schema::hasColumn('opname_items', $column)) {
                Schema::table('opname_items', fn (Blueprint $table) => $definition($table));
            }
        }

        Schema::table('opname_items', function (Blueprint $table): void {
            $table->index('opname_session_id', 'idx_opname_items_session');
        });
    }
};
