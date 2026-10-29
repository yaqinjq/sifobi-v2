<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\Request;

/**
 * Resolusi ?sort=&direction= dari query string, divalidasi lewat allowlist
 * kolom per halaman (supaya tidak bisa orderBy() ke kolom sembarangan lewat
 * URL) -- pola yang sudah terbukti dipakai ItemController::index(), ditarik
 * jadi satu sumber kebenaran supaya konsisten dipakai ulang di semua
 * halaman tabel lain. Dipasangkan dengan <x-sf.sortable-th> di view.
 */
trait ResolvesTableSort
{
    /**
     * @param  list<string>  $sortable  Kolom yang boleh dipakai sort, HARUS
     *                                  nama kolom DB asli (dipakai langsung
     *                                  di orderBy()), bukan input bebas.
     * @return array{0: string, 1: string} [$sort, $direction]
     */
    private function resolveTableSort(Request $request, array $sortable, string $default, string $defaultDirection = 'asc'): array
    {
        $requested = $request->string('sort')->toString();
        $sort = in_array($requested, $sortable, true) ? $requested : $default;

        $requestedDirection = $request->string('direction')->toString();
        $direction = in_array($requestedDirection, ['asc', 'desc'], true) ? $requestedDirection : $defaultDirection;

        return [$sort, $direction];
    }
}
