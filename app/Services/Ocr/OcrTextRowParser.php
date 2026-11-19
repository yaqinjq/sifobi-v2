<?php

namespace App\Services\Ocr;

/**
 * Parsing heuristik murni (tanpa facade/I-O apa pun) dari teks mentah hasil
 * OCR (mis. keluaran CLI tesseract) jadi baris item+qty+satuan. Sengaja
 * dipisah dari TesseractOcrEngine supaya logic parsing-nya bisa diuji tanpa
 * perlu binary tesseract terpasang sama sekali.
 *
 * Tesseract tidak "mengerti" struktur tabel -- cuma baca teks per baris --
 * jadi heuristik ini best-effort: akurasinya jauh di bawah engine vision
 * (ClaudeVisionOcrEngine) utk foto yang miring/buram/format tabel bebas.
 * Ini batasan yang disadari & diterima, bukan bug.
 */
class OcrTextRowParser
{
    private const NOISE_PATTERN = '/\b(detail order|total|subtotal|jumlah|halaman|page|tanggal|no\.?\s*(do|order|po)|dibuat oleh|diterima oleh|diperiksa|disetujui|catatan|keterangan)\b/iu';

    private const UNIT_WORDS = 'pcs|pack|box|dus|karton|kg|gr|gram|ltr|liter|btl|lusin|unit|pc';

    /**
     * @return list<array{raw_name: string, qty: float|null, unit_text: string|null, confidence: float}>
     */
    public function parse(string $rawText): array
    {
        $rows = [];

        foreach (preg_split('/\r\n|\r|\n/', $rawText) ?: [] as $line) {
            $line = trim($line);

            if ($line === '' || mb_strlen($line) < 3) {
                continue;
            }

            if (preg_match(self::NOISE_PATTERN, $line) === 1) {
                continue;
            }

            $row = $this->parseLine($line);

            if ($row !== null) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    /**
     * @return array{raw_name: string, qty: float|null, unit_text: string|null, confidence: float}|null
     */
    private function parseLine(string $line): ?array
    {
        $numberPattern = '\d+(?:[.,]\d+)?';

        // Qty di akhir baris: "Nama Barang   10 pcs" / "Nama Barang 10"
        if (preg_match('/^(?<name>.*\D)\s+(?<qty>'.$numberPattern.')\s*(?<unit>'.self::UNIT_WORDS.')?\.?\s*$/iu', $line, $m) === 1) {
            return $this->buildRow($m['name'], $m['qty'], $m['unit'] ?? null);
        }

        // Qty di awal baris: "10 pcs Nama Barang" / "10 Nama Barang"
        if (preg_match('/^(?<qty>'.$numberPattern.')\s*(?<unit>'.self::UNIT_WORDS.')?\s+(?<name>\D.*)$/iu', $line, $m) === 1) {
            return $this->buildRow($m['name'], $m['qty'], $m['unit'] ?? null);
        }

        // Tidak ada angka yang jelas jadi qty -- tetap tampilkan baris ini
        // (staf bisa isi qty manual) kalau tidak mengandung angka sama
        // sekali; kalau ada angka tapi tidak match pola di atas (mis. kode
        // produk bercampur huruf-angka), masih dianggap nama polos.
        if (preg_match('/\D/', $line) === 1) {
            return $this->buildRow($line, null, null);
        }

        return null;
    }

    /**
     * @return array{raw_name: string, qty: float|null, unit_text: string|null, confidence: float}|null
     */
    private function buildRow(string $rawName, ?string $qtyToken, ?string $unitToken): ?array
    {
        $name = trim(preg_replace('/\s+/', ' ', $rawName) ?? '', " \t\n\r\0\x0B-:.,");

        if (mb_strlen($name) < 3) {
            return null;
        }

        $qty = $qtyToken !== null ? (float) str_replace(',', '.', $qtyToken) : null;

        return [
            'raw_name' => $name,
            'qty' => $qty,
            'unit_text' => $unitToken !== null ? mb_strtolower($unitToken) : null,
            'confidence' => $qty !== null ? 0.6 : 0.3,
        ];
    }
}
