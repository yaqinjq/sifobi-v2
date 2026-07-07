<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table): void {
            if (! Schema::hasColumn('items', 'photo')) {
                $table->string('photo')->nullable()->after('description');
            }

            if (! Schema::hasColumn('items', 'keterangan_pembeda')) {
                $table->string('keterangan_pembeda')->nullable()->after('description');
            }

            if (! Schema::hasColumn('items', 'item_jenis')) {
                $table->string('item_jenis', 100)->nullable()->after('item_type');
            }

            if (! Schema::hasColumn('items', 'opname_frequency')) {
                $table->enum('opname_frequency', ['DAILY', 'WEEKLY', 'MONTHLY'])
                    ->default('DAILY')
                    ->after('yield_pct');
            }

            if (! Schema::hasColumn('items', 'primary_department_id')) {
                $table->unsignedBigInteger('primary_department_id')->nullable()->after('opname_frequency');
                $table->index(['tenant_id', 'primary_department_id'], 'idx_items_tenant_primary_dept');
            }

            if (! Schema::hasColumn('items', 'track_expiry')) {
                $table->boolean('track_expiry')->default(false)->after('primary_department_id');
            }
        });

        if (! Schema::hasTable('item_departments')) {
            Schema::create('item_departments', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('item_id');
                $table->unsignedBigInteger('department_id');
                $table->timestamps();

                $table->foreign('item_id', 'fk_item_depts_item')
                    ->references('id')
                    ->on('items')
                    ->cascadeOnDelete();
                $table->foreign('department_id', 'fk_item_depts_dept')
                    ->references('id')
                    ->on('departments')
                    ->cascadeOnDelete();
                $table->unique(['item_id', 'department_id'], 'uq_item_depts_scope');
            });
        }

        if (Schema::hasTable('item_outlets') && ! Schema::hasColumn('item_outlets', 'opname_frequency')) {
            Schema::table('item_outlets', function (Blueprint $table): void {
                $table->enum('opname_frequency', ['DAILY', 'WEEKLY', 'MONTHLY'])
                    ->nullable()
                    ->after('status');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('item_departments')) {
            Schema::dropIfExists('item_departments');
        }

        Schema::table('items', function (Blueprint $table): void {
            if (Schema::hasColumn('items', 'primary_department_id')) {
                $table->dropIndex('idx_items_tenant_primary_dept');
            }

            $columns = array_values(array_filter([
                Schema::hasColumn('items', 'photo') ? 'photo' : null,
                Schema::hasColumn('items', 'keterangan_pembeda') ? 'keterangan_pembeda' : null,
                Schema::hasColumn('items', 'item_jenis') ? 'item_jenis' : null,
                Schema::hasColumn('items', 'opname_frequency') ? 'opname_frequency' : null,
                Schema::hasColumn('items', 'primary_department_id') ? 'primary_department_id' : null,
                Schema::hasColumn('items', 'track_expiry') ? 'track_expiry' : null,
            ]));

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
