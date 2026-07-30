<?php

namespace App\Imports;

use App\Modules\Inventory\Models\Item;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class ItemPoTagsImport implements ToCollection, WithHeadingRow
{
    use Importable;

    private const VALID_TAGS = ['OCIA_ROASTERY', 'CENTRAL_KITCHEN'];

    private Collection $rows;

    public function collection(Collection $rows): void
    {
        $this->rows = $rows;
    }

    /**
     * Parse loaded rows dan bandingkan dengan data DB.
     * Kembalikan preview lengkap: item yang berubah, tidak berubah, dan tidak ditemukan.
     *
     * @return array{changes: list<array<string,mixed>>, total: int, changed: int, not_found: int}
     */
    public function preview(int $tenantId): array
    {
        $rows = $this->rows ?? collect();

        // SKU → raw tujuan_po string dari file
        $rowsBySku = [];
        foreach ($rows as $row) {
            $sku = trim((string) ($row['canonical_sku'] ?? ''));
            if ($sku === '') {
                continue;
            }
            $rowsBySku[$sku] = (string) ($row['tujuan_po'] ?? '');
        }

        if (empty($rowsBySku)) {
            return ['changes' => [], 'total' => 0, 'changed' => 0, 'not_found' => 0];
        }

        $items = Item::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('canonical_sku', array_keys($rowsBySku))
            ->with('inventoryUnit')
            ->get()
            ->keyBy('canonical_sku');

        $changes    = [];
        $notFound   = 0;

        foreach ($rowsBySku as $sku => $rawTags) {
            $item = $items->get($sku);

            if (! $item) {
                $notFound++;
                $changes[] = [
                    'sku'       => $sku,
                    'name'      => '(SKU tidak ditemukan)',
                    'unit_code' => '',
                    'old_tags'  => [],
                    'new_tags'  => [],
                    'has_change'=> false,
                    'not_found' => true,
                ];
                continue;
            }

            $oldTags = $this->normalizeTags($item->po_destinations ?? []);
            $newTags = $this->parseTags($rawTags);

            $changes[] = [
                'sku'       => $sku,
                'name'      => $item->name,
                'unit_code' => $item->inventoryUnit?->code ?? '',
                'old_tags'  => $oldTags,
                'new_tags'  => $newTags,
                'has_change'=> $oldTags !== $newTags,
                'not_found' => false,
            ];
        }

        return [
            'changes'   => $changes,
            'total'     => count($changes),
            'changed'   => count(array_filter($changes, fn ($c) => $c['has_change'])),
            'not_found' => $notFound,
        ];
    }

    /**
     * Terapkan perubahan yang sudah dikonfirmasi user.
     * Hanya proses item yang has_change === true; validasi ulang new_tags di server.
     *
     * @param  list<array<string,mixed>>  $changes
     * @return array{updated: int, skipped: int}
     */
    public static function applyChanges(array $changes, int $tenantId): array
    {
        $updated = 0;
        $skipped = 0;

        foreach ($changes as $change) {
            if (! ($change['has_change'] ?? false)) {
                $skipped++;
                continue;
            }

            $sku     = trim((string) ($change['sku'] ?? ''));
            $rawTags = $change['new_tags'] ?? [];

            if ($sku === '') {
                continue;
            }

            // Validasi ulang tag di server — jangan percaya nilai dari client mentah-mentah
            $safeTags = array_values(array_filter(
                (array) $rawTags,
                fn ($t) => in_array($t, self::VALID_TAGS, true)
            ));
            sort($safeTags);

            $affected = Item::query()
                ->where('tenant_id', $tenantId)
                ->where('canonical_sku', $sku)
                ->update([
                    'po_destinations' => empty($safeTags) ? null : json_encode(array_values($safeTags)),
                ]);

            $updated += $affected;
        }

        return ['updated' => $updated, 'skipped' => $skipped];
    }

    /**
     * @return list<string>
     */
    private function parseTags(string $raw): array
    {
        if (trim($raw) === '') {
            return [];
        }

        $tags = array_values(array_filter(
            array_map('trim', explode('|', strtoupper($raw))),
            fn ($t) => in_array($t, self::VALID_TAGS, true)
        ));

        sort($tags);

        return $tags;
    }

    /**
     * @param  array<mixed>  $tags
     * @return list<string>
     */
    private function normalizeTags(array $tags): array
    {
        $valid = array_values(array_filter(
            $tags,
            fn ($t) => in_array($t, self::VALID_TAGS, true)
        ));

        sort($valid);

        return $valid;
    }
}
