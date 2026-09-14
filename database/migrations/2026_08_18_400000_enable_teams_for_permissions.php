<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Aktifkan fitur "Teams" bawaan spatie/laravel-permission supaya role
     * (ADMIN, STAFF_BAR, dst) benar-benar terisolasi per tenant — sebelum
     * ini, roles/permissions dibagikan global lintas tenant. Skema di
     * bawah meniru persis vendor/spatie/laravel-permission/database/
     * migrations/add_teams_fields.php.stub, TAPI dengan tambahan backfill
     * eksplisit untuk roles.team_id (stub aslinya membiarkan kolom itu
     * NULL untuk baris lama — di app ini kita sengaja isi semua baris
     * lama dengan team_id=1 karena satu-satunya tenant yang ada sampai
     * migration ini dijalankan adalah MKO (id=1), dikonfirmasi via query
     * langsung sebelum menulis migration ini).
     */
    public function up(): void
    {
        // Fresh install/testing menjalankan migration di atas dengan
        // config/permission.php 'teams' => true SUDAH aktif sejak awal,
        // jadi create_permission_tables.php di atas langsung membuat
        // roles/model_has_permissions/model_has_roles dengan skema
        // team-scoped (kolom team_id + primary key gabungan) tanpa perlu
        // migration ini. ALTER di bawah akan gagal (duplicate column/
        // duplicate primary key) kalau dipaksa jalan lagi di kondisi itu —
        // jadi migration ini no-op di situ, cuma benar-benar bekerja saat
        // upgrade dari skema lama (production, dibuat sebelum teams aktif).
        if (Schema::hasColumn('roles', 'team_id')) {
            return;
        }

        Schema::table('roles', function (Blueprint $table): void {
            $table->unsignedBigInteger('team_id')->nullable()->after('id');
            $table->index('team_id', 'roles_team_foreign_key_index');

            $table->dropUnique('roles_name_guard_name_unique');
            $table->unique(['team_id', 'name', 'guard_name'], 'roles_team_id_name_guard_name_unique');
        });

        // Semua role yang sudah ada sebelum fitur ini adalah milik MKO (tenant 1).
        DB::table('roles')->update(['team_id' => 1]);

        Schema::table('model_has_permissions', function (Blueprint $table): void {
            $table->unsignedBigInteger('team_id')->default('1');
            $table->index('team_id', 'model_has_permissions_team_foreign_key_index');

            if (DB::getDriverName() !== 'sqlite') {
                $table->dropForeign(['permission_id']);
            }
            $table->dropPrimary();

            $table->primary(
                ['team_id', 'permission_id', 'model_id', 'model_type'],
                'model_has_permissions_permission_model_type_primary'
            );

            if (DB::getDriverName() !== 'sqlite') {
                $table->foreign('permission_id')->references('id')->on('permissions')->cascadeOnDelete();
            }
        });

        Schema::table('model_has_roles', function (Blueprint $table): void {
            $table->unsignedBigInteger('team_id')->default('1');
            $table->index('team_id', 'model_has_roles_team_foreign_key_index');

            if (DB::getDriverName() !== 'sqlite') {
                $table->dropForeign(['role_id']);
            }
            $table->dropPrimary();

            $table->primary(
                ['team_id', 'role_id', 'model_id', 'model_type'],
                'model_has_roles_role_model_type_primary'
            );

            if (DB::getDriverName() !== 'sqlite') {
                $table->foreign('role_id')->references('id')->on('roles')->cascadeOnDelete();
            }
        });

        app('cache')
            ->store(config('permission.cache.store') != 'default' ? config('permission.cache.store') : null)
            ->forget(config('permission.cache.key'));
    }

    public function down(): void
    {
        // Simetris dengan up(): kalau migration ini no-op saat up() (skema
        // sudah team-scoped sejak dibuat create_permission_tables.php),
        // down() juga harus no-op — supaya tidak menghapus kolom/primary
        // key yang justru bukan migration ini yang membuatnya.
        if (! in_array('roles_name_guard_name_unique', Schema::getIndexListing('roles'), true)) {
            return;
        }

        Schema::table('model_has_roles', function (Blueprint $table): void {
            if (DB::getDriverName() !== 'sqlite') {
                $table->dropForeign(['role_id']);
            }
            $table->dropPrimary('model_has_roles_role_model_type_primary');
            $table->dropColumn('team_id');
            $table->primary(['role_id', 'model_id', 'model_type']);
            if (DB::getDriverName() !== 'sqlite') {
                $table->foreign('role_id')->references('id')->on('roles')->cascadeOnDelete();
            }
        });

        Schema::table('model_has_permissions', function (Blueprint $table): void {
            if (DB::getDriverName() !== 'sqlite') {
                $table->dropForeign(['permission_id']);
            }
            $table->dropPrimary('model_has_permissions_permission_model_type_primary');
            $table->dropColumn('team_id');
            $table->primary(['permission_id', 'model_id', 'model_type']);
            if (DB::getDriverName() !== 'sqlite') {
                $table->foreign('permission_id')->references('id')->on('permissions')->cascadeOnDelete();
            }
        });

        Schema::table('roles', function (Blueprint $table): void {
            $table->dropUnique('roles_team_id_name_guard_name_unique');
            $table->dropColumn('team_id');
            $table->unique(['name', 'guard_name'], 'roles_name_guard_name_unique');
        });
    }
};
