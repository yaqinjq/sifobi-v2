<?php

namespace App\Services\Ocr;

use App\Modules\Core\Models\IntegrationProfile;
use App\Modules\Inventory\Models\Item;

/**
 * Resolver + orchestrator OCR dokumen penerimaan barang. Satu-satunya
 * tempat yang perlu diubah kalau nanti nambah engine ke-3 -- lihat
 * engineFor().
 */
class DocumentOcrService
{
    public function __construct(private ItemFuzzyMatcher $matcher) {}

    public function engineFor(int $tenantId): DocumentOcrEngine
    {
        $profile = IntegrationProfile::query()
            ->where('tenant_id', $tenantId)
            ->where('code', 'OCR_CLAUDE')
            ->where('is_active', true)
            ->first();

        return $profile
            ? new ClaudeVisionOcrEngine($profile)
            : app(TesseractOcrEngine::class);
    }

    /**
     * @return list<array{item_id: ?int, match_score: float, raw_name: string, qty: ?float, unit_text: ?string, confidence: float}>
     */
    public function suggestRows(int $tenantId, string $imagePath): array
    {
        $rawRows = $this->engineFor($tenantId)->extractRows($imagePath);

        $items = Item::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->get(['id', 'name', 'canonical_sku']);

        return array_map(function (array $row) use ($items): array {
            $match = $this->matcher->match($row['raw_name'], $items);

            return [
                'item_id' => $match['item_id'] ?? null,
                'match_score' => $match['score'] ?? 0.0,
                'raw_name' => $row['raw_name'],
                'qty' => $row['qty'],
                'unit_text' => $row['unit_text'],
                'confidence' => $row['confidence'],
            ];
        }, $rawRows);
    }
}
