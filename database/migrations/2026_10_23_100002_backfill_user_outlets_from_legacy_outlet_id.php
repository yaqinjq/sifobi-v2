<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Setiap user yang outlet_id-nya SUDAH TERISI dapat 1 baris
     * user_outlets yang sepadan -- supaya perilaku user single-outlet
     * yang sudah ada 100% tidak berubah lewat pivot baru ini (lihat
     * HasOutletAccess::accessibleOutletIds()). User dengan outlet_id
     * kosong (akses global/semua outlet) TIDAK dapat baris pivot.
     */
    public function up(): void
    {
        DB::table('users')->whereNotNull('outlet_id')->orderBy('id')->chunkById(500, function ($users): void {
            $rows = $users->map(fn ($user) => [
                'user_id' => $user->id,
                'outlet_id' => $user->outlet_id,
                'created_at' => now(),
                'updated_at' => now(),
            ])->all();

            if ($rows !== []) {
                DB::table('user_outlets')->insertOrIgnore($rows);
            }
        });
    }

    /**
     * Rollback cuma menghapus baris yang PERSIS cocok dengan outlet_id
     * legacy saat ini -- supaya tidak menghapus baris pivot yang sudah
     * dibuat lewat UI multi-outlet baru (mis. user yang ditambahkan ke
     * outlet kedua setelah migration ini jalan).
     */
    public function down(): void
    {
        // Loop per user (bukan DELETE...JOIN) supaya portable ke SQLite
        // (dipakai tests) selain MySQL, DAN supaya cocok persis per
        // pasangan user+outlet -- bukan "outlet_id yang kebetulan sama
        // dengan user lain", yang berisiko salah hapus kalau 2 user
        // beda kebetulan punya outlet_id legacy yang sama.
        DB::table('users')->whereNotNull('outlet_id')->orderBy('id')->chunkById(500, function ($users): void {
            foreach ($users as $user) {
                DB::table('user_outlets')
                    ->where('user_id', $user->id)
                    ->where('outlet_id', $user->outlet_id)
                    ->delete();
            }
        });
    }
};
