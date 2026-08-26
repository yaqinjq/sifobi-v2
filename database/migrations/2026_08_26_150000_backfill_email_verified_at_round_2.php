<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Migration pertama (2026_08_26_020001) backfill email_verified_at untuk
// user yang sudah ada SEBELUM fitur verifikasi email deploy. Tapi 2 jalur
// pembuatan user lain — Admin\TenantController::store() (admin tenant
// baru) dan Settings\UserController::store() (tambah user manual) —
// ternyata belum ikut di-set email_verified_at saat itu, jadi user yang
// dibuat lewat 2 jalur itu SETELAH deploy pertama tetap NULL dan
// terkunci login ("Email belum diverifikasi") padahal statusnya ACTIVE.
// Kedua controller sudah diperbaiki di commit yang sama dengan migration
// ini supaya user baru ke depannya otomatis ke-set — migration ini cuma
// menambal user yang terlanjur dibuat di jendela waktu antara deploy
// pertama dan perbaikan ini.
return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')
            ->whereNull('email_verified_at')
            ->update(['email_verified_at' => now()]);
    }

    public function down(): void
    {
        // Sengaja tidak ada rollback, sama seperti migration backfill pertama.
    }
};
