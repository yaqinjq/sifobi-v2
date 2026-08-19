<?php

namespace App\Imports;

use App\Modules\Inventory\Models\Item;
use App\Modules\Inventory\Models\ItemCategory;
use App\Modules\Inventory\Models\Unit;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Throwable;

/**
 * Reads a Wipro catalog Excel (multi-sheet: one sheet per category).
 * Columns: A=sku, B=product_name, C=product_type, D=unit, E=is_active, F=kategori, G=CK, H=konversi
 * Row 1 is the heading row — data starts at row 2.
 *
 * Kolom H (header-nya bisa "Konversi" atau masih "outlet" tergantung sheet
 * — dibaca berdasarkan posisi kolom, bukan nama header, jadi tidak masalah)
 * berisi teks bebas format "1 PACK = 2 KG" — dipakai untuk isi purchase_unit
 * (mis. PACK) terpisah dari base/inventory_unit (mis. KG) beserta rasionya.
 * Kalau kolom ini kosong/tidak bisa diparse (tim WIP masih proses isi data
 * untuk sebagian item), fallback ke perilaku lama: base=inventory=purchase
 * unit sama persis, ratio 1 — supaya tidak merusak item yang datanya belum
 * lengkap.
 */
class WiproCatalogImport
{
    private int   $inserted = 0;
    private int   $updated  = 0;
    private array $errors   = [];

    public function __construct(private readonly int $tenantId) {}

    public function import(string $filePath): void
    {
        $spreadsheet = IOFactory::load($filePath);

        foreach ($spreadsheet->getAllSheets() as $sheet) {
            $highestRow = $sheet->getHighestDataRow();

            // Start from row 2 (skip header)
            for ($rowNum = 2; $rowNum <= $highestRow; $rowNum++) {
                $sku = trim((string) $sheet->getCell("A{$rowNum}")->getValue());

                if ($sku === '') {
                    continue;
                }

                try {
                    $this->upsert(
                        sku:       $sku,
                        name:      trim((string) $sheet->getCell("B{$rowNum}")->getValue()),
                        unitCode:  strtoupper(trim((string) $sheet->getCell("D{$rowNum}")->getValue())) ?: 'PCS',
                        kategori:  trim((string) $sheet->getCell("F{$rowNum}")->getValue()),
                        isActive:  ((int) $sheet->getCell("E{$rowNum}")->getValue()) !== 0,
                        konversi:  trim((string) $sheet->getCell("H{$rowNum}")->getValue()),
                    );
                } catch (Throwable $e) {
                    $this->errors[] = "Sheet [{$sheet->getTitle()}] baris {$rowNum} (SKU {$sku}): " . $e->getMessage();
                }
            }
        }
    }

    /** @return array{inserted:int,updated:int,errors:list<string>} */
    public function summary(): array
    {
        return [
            'inserted' => $this->inserted,
            'updated'  => $this->updated,
            'errors'   => $this->errors,
        ];
    }

    private function upsert(string $sku, string $name, string $unitCode, string $kategori, bool $isActive, string $konversi = ''): void
    {
        $purchaseUnit = $this->resolveUnit($unitCode);
        $category     = $this->resolveCategory($kategori);

        $conversion = $this->parseConversion($konversi);

        if ($conversion) {
            $baseUnit = $this->resolveUnit($conversion['base_unit_code']);

            $attributes = [
                'name'              => $name,
                'item_type'         => 'WIP_L1',
                'inventory_unit_id' => $baseUnit->id,
                'purchase_unit_id'  => $purchaseUnit->id,
                'base_unit_id'      => $baseUnit->id,
                'item_category_id'  => $category?->id,
                'is_active'         => $isActive,
                'track_stock'       => false,
                'po_destinations'   => ['CENTRAL_KITCHEN'],
                'item_source'       => 'WIPRO',
                'inventory_ratio'   => 1,
                'purchase_ratio'    => $conversion['ratio'],
            ];
        } else {
            // Belum ada data konversi untuk item ini (tim WIP masih proses) —
            // fallback lama: satu unit yang sama untuk base/inventory/purchase.
            $attributes = [
                'name'              => $name,
                'item_type'         => 'WIP_L1',
                'inventory_unit_id' => $purchaseUnit->id,
                'purchase_unit_id'  => $purchaseUnit->id,
                'base_unit_id'      => $purchaseUnit->id,
                'item_category_id'  => $category?->id,
                'is_active'         => $isActive,
                'track_stock'       => false,
                'po_destinations'   => ['CENTRAL_KITCHEN'],
                'item_source'       => 'WIPRO',
                'inventory_ratio'   => 1,
                'purchase_ratio'    => 1,
            ];
        }

        $existing = Item::withoutGlobalScopes()
            ->where('tenant_id', $this->tenantId)
            ->where('canonical_sku', $sku)
            ->first();

        if ($existing) {
            $existing->update($attributes);
            $this->updated++;
        } else {
            Item::withoutGlobalScopes()->create(array_merge($attributes, [
                'tenant_id'     => $this->tenantId,
                'canonical_sku' => $sku,
            ]));
            $this->inserted++;
        }
    }

    /**
     * Parse teks bebas "1 PACK = 2 KG" (spasi longgar, case-insensitive) jadi
     * ['base_unit_code' => 'KG', 'ratio' => 2.0] — ratio = berapa base_unit
     * per 1 purchase_unit. Return null kalau kosong/tidak bisa diparse.
     *
     * @return array{base_unit_code: string, ratio: float}|null
     */
    private function parseConversion(string $text): ?array
    {
        if ($text === '') {
            return null;
        }

        $normalized = str_replace(',', '.', $text);

        if (! preg_match('/^\s*([\d.]+)\s*[A-Za-z]+\s*=\s*([\d.]+)\s*([A-Za-z]+)\s*$/i', $normalized, $m)) {
            return null;
        }

        $purchaseQty = (float) $m[1];
        $baseQty     = (float) $m[2];
        $baseUnitCode = strtoupper($m[3]);

        if ($purchaseQty <= 0 || $baseQty <= 0) {
            return null;
        }

        return [
            'base_unit_code' => $baseUnitCode,
            'ratio' => round($baseQty / $purchaseQty, 6),
        ];
    }

    private function resolveUnit(string $code): Unit
    {
        $unit = Unit::query()
            ->where('tenant_id', $this->tenantId)
            ->where(fn ($q) => $q->where('code', $code)->orWhere('code', strtolower($code)))
            ->first();

        if (! $unit) {
            $unit = Unit::query()->create([
                'tenant_id'    => $this->tenantId,
                'code'         => $code,
                'name'         => $code,
                'abbreviation' => strtolower($code),
            ]);
        }

        return $unit;
    }

    private function resolveCategory(string $name): ?ItemCategory
    {
        if ($name === '') {
            return null;
        }

        $code = 'WIPRO_' . Str::of($name)->upper()->replaceMatches('/[^A-Z0-9]+/', '_')->trim('_')->limit(28, '')->toString();

        return ItemCategory::withoutGlobalScopes()->updateOrCreate(
            ['tenant_id' => $this->tenantId, 'code' => $code],
            ['name' => 'Wipro — ' . Str::title($name), 'status' => 'ACTIVE', 'is_active' => true]
        );
    }
}
