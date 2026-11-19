<?php

namespace App\Services\Ocr;

interface DocumentOcrEngine
{
    /**
     * Baca foto dokumen (surat jalan/SJ) dan kembalikan baris-baris item
     * yang terdeteksi. Ini SARAN mentah -- belum dicocokkan ke Item master
     * (lihat ItemFuzzyMatcher) dan tidak pernah dipakai langsung tanpa
     * review staf.
     *
     * @return list<array{raw_name: string, qty: float|null, unit_text: string|null, confidence: float}>
     */
    public function extractRows(string $imagePath): array;
}
