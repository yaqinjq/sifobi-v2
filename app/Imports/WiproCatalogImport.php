<?php

namespace App\Imports;

use App\Modules\Inventory\Models\Item;
use App\Modules\Inventory\Models\ItemCategory;
use App\Modules\Inventory\Models\Unit;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Throwable;

/**
 * Reads a Wipro catalog Excel (multi-sheet: one sheet per category).
 *
 * Kolom dibaca berdasarkan TEKS HEADER di row 1 (lihat detectColumns()),
 * BUKAN posisi huruf kolom tetap lagi -- sebelumnya hardcoded A=sku,
 * B=product_name, C=product_type, D=unit, E=is_active, F=kategori,
 * G=CK, H=konversi, yang diam-diam menghasilkan data SALAH TOTAL (bukan
 * error yang kelihatan) begitu file "STANDARISASI PRODUK WIP.xlsx" dari
 * pimpinan menyisipkan kolom baru (Product_Code di depan, outlet sebelum
 * konversi) sehingga semua posisi kolom bergeser. Kolom Product_Code, CK,
 * dan outlet sengaja tidak dipakai (bukan bagian dari data yang disimpan
 * ke Item), cukup kolom SKU/Product_Name/Unit/Is_Active/Kategori/Konversi
 * yang wajib ketemu lewat nama headernya.
 *
 * Kolom konversi berisi teks bebas format "1 PACK = 2 KG" — dipakai untuk
 * isi purchase_unit (mis. PACK) terpisah dari base/inventory_unit
 * (mis. KG) beserta rasionya. Kalau kolom ini kosong/tidak bisa diparse
 * (tim WIP masih proses isi data untuk sebagian item), fallback ke
 * perilaku lama: base=inventory=purchase unit sama persis, ratio 1 —
 * supaya tidak merusak item yang datanya belum lengkap.
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
            $columns = $this->detectColumns($sheet);

            if (! $columns) {
                $this->errors[] = "Sheet [{$sheet->getTitle()}]: format kolom tidak dikenali -- pastikan ada header SKU, Product_Name, (Default_)Unit(_Symbol), Is_Active, dan Kategori.";

                continue;
            }

            $highestRow = $sheet->getHighestDataRow();

            for ($rowNum = $columns['header'] + 1; $rowNum <= $highestRow; $rowNum++) {
                $sku = trim((string) $sheet->getCell("{$columns['sku']}{$rowNum}")->getValue());

                if ($sku === '') {
                    continue;
                }

                try {
                    $this->upsert(
                        sku:       $sku,
                        name:      trim((string) $sheet->getCell("{$columns['name']}{$rowNum}")->getValue()),
                        unitCode:  strtoupper(trim((string) $sheet->getCell("{$columns['unit']}{$rowNum}")->getValue())) ?: 'PCS',
                        kategori:  trim((string) $sheet->getCell("{$columns['kategori']}{$rowNum}")->getValue()),
                        isActive:  ((int) $sheet->getCell("{$columns['active']}{$rowNum}")->getValue()) !== 0,
                        konversi:  isset($columns['konversi']) ? trim((string) $sheet->getCell("{$columns['konversi']}{$rowNum}")->getValue()) : '',
                    );
                } catch (Throwable $e) {
                    $this->errors[] = "Sheet [{$sheet->getTitle()}] baris {$rowNum} (SKU {$sku}): " . $e->getMessage();
                }
            }
        }
    }

    /**
     * Cari baris header (mengandung kolom SKU/Product_Name/Unit/Is_Active/
     * Kategori) dan kolom-kolom yang relevan berdasarkan teks header-nya --
     * bukan posisi tetap -- supaya tahan terhadap kolom baru yang disisipkan
     * di tengah (persis yang terjadi di file "STANDARISASI PRODUK WIP.xlsx").
     * Kolom Konversi opsional (item tanpa data konversi tetap bisa diimpor).
     *
     * @return array{header:int,sku:string,name:string,unit:string,active:string,kategori:string,konversi?:string}|null
     */
    private function detectColumns(Worksheet $sheet): ?array
    {
        $highestRow = min($sheet->getHighestRow(), 5);
        $highestColumn = $sheet->getHighestColumn();

        for ($rowNum = 1; $rowNum <= $highestRow; $rowNum++) {
            $found = [];

            foreach ($this->columnRange('A', $highestColumn) as $col) {
                $value = strtoupper(trim((string) $sheet->getCell("{$col}{$rowNum}")->getValue()));

                if ($value === '') {
                    continue;
                }

                if ($value === 'SKU') {
                    $found['sku'] = $col;
                } elseif (str_contains($value, 'PRODUCT_NAME') || $value === 'NAME') {
                    $found['name'] = $col;
                } elseif (str_contains($value, 'UNIT')) {
                    $found['unit'] = $col;
                } elseif (str_contains($value, 'ACTIVE')) {
                    $found['active'] = $col;
                } elseif (str_contains($value, 'KATEGORI') || str_contains($value, 'CATEGORY')) {
                    $found['kategori'] = $col;
                } elseif (str_contains($value, 'KONVERSI') || str_contains($value, 'CONVERSION')) {
                    $found['konversi'] = $col;
                }
            }

            if (isset($found['sku'], $found['name'], $found['unit'], $found['active'], $found['kategori'])) {
                return ['header' => $rowNum] + $found;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function columnRange(string $from, string $to): array
    {
        $columns = [];
        $current = $from;

        while (true) {
            $columns[] = $current;

            if ($current === $to) {
                break;
            }

            $current = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(
                \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($current) + 1
            );

            if (count($columns) > 100) {
                break;
            }
        }

        return $columns;
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

        // SKU dari Wipro ternyata bisa kebetulan sama dengan SKU item bahan
        // baku internal yang dibuat manual dan sama sekali tidak berkaitan
        // (kejadian nyata: BMB-TMYM dipakai Wipro untuk "Bumbu Tomyum" versi
        // mereka, TAPI kode yang sama juga sudah lebih dulu dipakai staff
        // untuk bahan baku internal yang berbeda produk). Kalau item yang
        // ketemu itu BUKAN item Wipro (item_source-nya bukan 'WIPRO'),
        // JANGAN disentuh sama sekali — itu bukan produk yang sama, cuma
        // kebetulan kodenya sama. Catat sebagai konflik biar admin sadar dan
        // bisa ganti salah satu kodenya secara manual, baru re-import.
        if ($existing && $existing->item_source !== 'WIPRO') {
            $this->errors[] = "SKU {$sku} ({$name}) sudah dipakai item lain yang BUKAN item Wipro "
                ."(\"{$existing->name}\", id {$existing->id}) — dilewati supaya tidak tertimpa. "
                .'Ganti salah satu kode SKU-nya secara manual, lalu import ulang.';

            return;
        }

        if ($existing) {
            // Item ini sudah pernah "diadopsi" manual oleh staff (dikasih
            // foto, track_stock dinyalakan, atau kategorinya diganti dari
            // hasil auto-sync "Wipro — X") — jangan timpa lagi kategori &
            // track_stock-nya supaya kustomisasi manual tidak hilang tiap
            // kali katalog di-upload ulang. Field lain (nama, satuan,
            // status aktif, rasio) tetap selalu ikut sumber Wipro.
            $isCustomized = (bool) $existing->photo
                || $existing->track_stock
                || ! str_starts_with($existing->category?->name ?? '', 'Wipro —');

            if ($isCustomized) {
                unset($attributes['item_category_id'], $attributes['track_stock']);
            }

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
