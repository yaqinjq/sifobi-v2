<?php

namespace App\Models\Concerns;

/**
 * Mendamaikan 2 konvensi lama "outlet_id kosong" yang SENGAJA berbeda
 * di seluruh app (permisif: "kosong = lihat semua" dipakai Opname/PO;
 * fail-closed: "kosong = TIDAK ADA akses" dipakai Spoil & Waste/
 * Inventory Simple, lihat EnforcesSuperAdminScope) dengan kasus baru
 * "user punya BEBERAPA outlet lewat pivot user_outlets" -- TANPA
 * mengubah kebijakan masing-masing konvensi lama.
 *
 * - Kode yang permisif (list/filter, "kosong = semua") pakai
 *   accessibleOutletIds().
 * - Kode yang butuh TEPAT 1 outlet per transaksi (fail-closed, mis.
 *   Spoil & Waste) pakai soleOutletId() -- SENGAJA balik null kalau
 *   user punya >1 outlet, bukan diam-diam pilih salah satu, supaya
 *   tidak mengulang bug yang sudah pernah diperbaiki di modul itu.
 */
trait HasOutletAccess
{
    /**
     * Urutan resolusi: ada baris pivot user_outlets -> itu (kasus
     * multi-outlet baru); tidak ada tapi outlet_id lama terisi ->
     * [outlet_id] (kompatibel mundur, user single-outlet lama);
     * keduanya kosong -> [] (akses global, perilaku tiap modul
     * tergantung konvensinya sendiri).
     *
     * @return list<int>
     */
    public function accessibleOutletIds(): array
    {
        $pivotIds = $this->outlets()->pluck('outlets.id')->map(fn ($id) => (int) $id)->all();

        if ($pivotIds !== []) {
            return $pivotIds;
        }

        return $this->outlet_id ? [(int) $this->outlet_id] : [];
    }

    public function hasSingleOutlet(): bool
    {
        return count($this->accessibleOutletIds()) === 1;
    }

    public function hasMultipleOutlets(): bool
    {
        return count($this->accessibleOutletIds()) > 1;
    }

    /**
     * Outlet tunggal yang berlaku untuk user ini, kalau memang cuma
     * ada 1 (legacy single-outlet ATAU pivot dengan 1 baris). Null
     * kalau 0 (akses global/tidak ada) atau >1 (multi-outlet, harus
     * pilih eksplisit -- lihat docblock trait ini).
     */
    public function soleOutletId(): ?int
    {
        $ids = $this->accessibleOutletIds();

        return count($ids) === 1 ? $ids[0] : null;
    }
}
