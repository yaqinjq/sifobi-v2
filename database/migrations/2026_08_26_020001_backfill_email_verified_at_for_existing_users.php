<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Kolom email_verified_at sudah ada dari migration bawaan Laravel tapi
// tidak pernah dipakai — semua user yang sudah ada dibuat langsung oleh
// admin (bukan lewat self-serve register yang baru ditambahkan), jadi
// dianggap terpercaya/terverifikasi dari awal. Tanpa backfill ini,
// LoginController yang sekarang mewajibkan email_verified_at akan
// mengunci SEMUA akun lama begitu fitur ini deploy.
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
        // Sengaja tidak ada rollback — tidak mungkin membedakan lagi
        // mana yang dulu NULL vs yang memang baru diverifikasi asli.
    }
};
