<?php

namespace App\Services\Ocr;

use App\Modules\Inventory\Models\Item;
use Illuminate\Support\Collection;

/**
 * Cocokkan 1 teks nama item hasil OCR (seringkali agak berantakan) ke Item
 * master tenant. Strategi tunggal, sengaja sederhana (similar_text() native
 * PHP, tanpa dependency baru) -- cukup toleran thd typo/noise ringan dari
 * OCR, sesuai target akurasi "mendekati", bukan 100%. Di bawah threshold ->
 * null, supaya UI jujur bilang "tidak dikenali" drpd menebak item yang salah
 * secara diam-diam.
 */
class ItemFuzzyMatcher
{
    /**
     * @param  Collection<int, Item>  $items
     * @return array{item_id: int, score: float}|null
     */
    public function match(string $rawName, Collection $items, float $threshold = 0.45): ?array
    {
        $needle = $this->normalize($rawName);

        if ($needle === '') {
            return null;
        }

        $best = null;
        $bestScore = 0.0;

        foreach ($items as $item) {
            $score = max(
                $this->similarity($needle, $this->normalize((string) $item->name)),
                $this->similarity($needle, $this->normalize((string) $item->canonical_sku)),
            );

            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $item;
            }
        }

        if ($best === null || $bestScore < $threshold) {
            return null;
        }

        return ['item_id' => (int) $best->id, 'score' => $bestScore];
    }

    private function similarity(string $a, string $b): float
    {
        if ($a === '' || $b === '') {
            return 0.0;
        }

        similar_text($a, $b, $percent);

        return $percent / 100;
    }

    private function normalize(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $value) ?? $value;

        return trim(preg_replace('/\s+/', ' ', $value) ?? $value);
    }
}
