<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\Request;

/**
 * Dropdown "Tampilkan X per halaman" yang konsisten di semua halaman
 * berpaginasi -- validasi per_page dari query string terhadap daftar opsi
 * yang diperbolehkan (bukan angka bebas), selalu menyertakan ukuran default
 * halaman itu sendiri supaya tidak "hilang" saat user beralih opsi lain.
 */
trait HasPerPageSelector
{
    /**
     * @return array{0: int, 1: list<int>}
     */
    protected function perPageAndOptions(Request $request, int $default = 25): array
    {
        $options = collect([$default, 25, 50, 100, 1000])
            ->unique()
            ->sort()
            ->values()
            ->all();

        $perPage = (int) $request->input('per_page', $default);
        $perPage = in_array($perPage, $options, true) ? $perPage : $default;

        return [$perPage, $options];
    }
}
