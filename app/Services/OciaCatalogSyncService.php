<?php

namespace App\Services;

use App\Modules\Inventory\Models\Item;
use App\Modules\Inventory\Models\ItemCategory;
use App\Modules\Inventory\Models\Unit;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Throwable;

/**
 * Upsert katalog roasted bean OCIA (dari OciaIntegrationService::pullCatalog())
 * ke tabel items, item_source = 'OCIA' -- pola yang sama persis dengan
 * WiproCatalogImport (lihat file itu utk penjelasan lengkap pola "adopsi
 * manual" & deteksi konflik SKU), diadaptasi dari Excel ke JSON API:
 *
 * - product_id (integer OCIA, STABIL) dicocokkan lewat kolom
 *   items.ocia_product_id -- BUKAN product_code, karena product_code
 *   (dipakai sebagai canonical_sku) bisa saja diganti/di-restandarisasi di
 *   sisi OCIA suatu saat, sama seperti kasus nyata SKU Wipro.
 * - 1 produk OCIA = 1 Item SIFOBI (keputusan eksplisit dari pimpinan) --
 *   data "packs" (varian kemasan/berat) TIDAK dibuatkan item terpisah,
 *   sengaja tidak disimpan sama sekali di tahap ini (bisa ditambahkan nanti
 *   kalau ternyata dibutuhkan, mis. sebagai info satuan pembelian).
 * - unit "kg" dipakai sebagai base=inventory=purchase unit sekaligus
 *   (rasio 1:1) -- OCIA tidak membedakan satuan beli vs satuan simpan di
 *   data katalognya.
 */
class OciaCatalogSyncService
{
    private int $inserted = 0;

    private int $updated = 0;

    /**
     * @var list<string>
     */
    private array $errors = [];

    public function __construct(private readonly int $tenantId)
    {
    }

    /**
     * @param  list<array<string, mixed>>  $records
     */
    public function sync(array $records): void
    {
        foreach ($records as $index => $record) {
            $productId = (int) Arr::get($record, 'product_id', 0);
            $productCode = trim((string) Arr::get($record, 'product_code', ''));
            $productName = trim((string) Arr::get($record, 'product_name', ''));

            if ($productId <= 0 || $productCode === '' || $productName === '') {
                $this->errors[] = 'Baris '.($index + 1).": product_id/product_code/product_name kosong, dilewati.";

                continue;
            }

            try {
                $this->upsert(
                    productId: $productId,
                    productCode: $productCode,
                    name: $productName,
                    isActive: (bool) Arr::get($record, 'is_active', true),
                    unitCode: strtoupper(trim((string) Arr::get($record, 'unit', 'kg'))) ?: 'KG',
                    komposisi: trim((string) Arr::get($record, 'komposisi', '')),
                    jenisKomposisi: trim((string) Arr::get($record, 'jenis_komposisi', '')),
                );
            } catch (Throwable $e) {
                $this->errors[] = "Produk {$productCode} ({$productName}): ".$e->getMessage();
            }
        }
    }

    /**
     * @return array{inserted:int,updated:int,errors:list<string>}
     */
    public function summary(): array
    {
        return [
            'inserted' => $this->inserted,
            'updated' => $this->updated,
            'errors' => $this->errors,
        ];
    }

    private function upsert(int $productId, string $productCode, string $name, bool $isActive, string $unitCode, string $komposisi, string $jenisKomposisi): void
    {
        $unit = $this->resolveUnit($unitCode);
        $category = $this->resolveCategory($komposisi, $jenisKomposisi);

        $attributes = [
            'name' => $name,
            'item_type' => 'BAHAN_BAKU',
            'inventory_unit_id' => $unit->id,
            'purchase_unit_id' => $unit->id,
            'base_unit_id' => $unit->id,
            'item_category_id' => $category?->id,
            'is_active' => $isActive,
            'track_stock' => true,
            'po_destinations' => ['OCIA_ROASTERY'],
            'item_source' => 'OCIA',
            'inventory_ratio' => 1,
            'purchase_ratio' => 1,
        ];

        // Cari lewat product_id (STABIL, lihat docblock kelas ini) dulu,
        // fallback ke canonical_sku kalau item ini belum pernah di-tag
        // ocia_product_id-nya (mis. sync pertama kali).
        $existing = Item::withoutGlobalScopes()
            ->where('tenant_id', $this->tenantId)
            ->where('ocia_product_id', $productId)
            ->first();

        if (! $existing) {
            $existing = Item::withoutGlobalScopes()
                ->where('tenant_id', $this->tenantId)
                ->where('canonical_sku', $productCode)
                ->first();
        }

        // SKU kebetulan sama dengan item lain yang BUKAN produk OCIA (mis.
        // bahan baku internal) -- jangan ditimpa, sama seperti
        // WiproCatalogImport menangani tabrakan SKU.
        if ($existing && $existing->item_source !== 'OCIA') {
            $this->errors[] = "Kode produk {$productCode} ({$name}) sudah dipakai item lain yang BUKAN item OCIA "
                ."(\"{$existing->name}\", id {$existing->id}) -- dilewati supaya tidak tertimpa. "
                .'Ganti salah satu kode SKU-nya secara manual, lalu sync ulang.';

            return;
        }

        $attributes['ocia_product_id'] = $productId;

        if ($existing) {
            if ($existing->canonical_sku !== $productCode) {
                $codeTakenByAnotherItem = Item::withoutGlobalScopes()
                    ->where('tenant_id', $this->tenantId)
                    ->where('canonical_sku', $productCode)
                    ->where('id', '!=', $existing->id)
                    ->exists();

                if ($codeTakenByAnotherItem) {
                    $this->errors[] = "Kode produk baru '{$productCode}' untuk \"{$name}\" sudah dipakai item lain -- "
                        ."dilewati, kode lama '{$existing->canonical_sku}' tetap dipakai. Periksa manual.";
                } else {
                    $attributes['canonical_sku'] = $productCode;
                }
            }

            // Item ini sudah "diadopsi" manual (dikasih foto, atau
            // kategorinya diganti dari hasil auto-sync "OCIA — X") --
            // jangan timpa lagi kategorinya supaya kustomisasi manual
            // tidak hilang tiap kali katalog di-sync ulang.
            $isCustomized = (bool) $existing->photo
                || ! str_starts_with($existing->category?->name ?? '', 'OCIA —');

            if ($isCustomized) {
                unset($attributes['item_category_id']);
            }

            $existing->update($attributes);
            $this->updated++;
        } else {
            Item::withoutGlobalScopes()->create(array_merge($attributes, [
                'tenant_id' => $this->tenantId,
                'canonical_sku' => $productCode,
            ]));
            $this->inserted++;
        }
    }

    private function resolveUnit(string $code): Unit
    {
        $unit = Unit::query()
            ->where('tenant_id', $this->tenantId)
            ->where(fn ($q) => $q->where('code', $code)->orWhere('code', strtolower($code)))
            ->first();

        if (! $unit) {
            $unit = Unit::query()->create([
                'tenant_id' => $this->tenantId,
                'code' => $code,
                'name' => $code,
                'abbreviation' => strtolower($code),
            ]);
        }

        return $unit;
    }

    private function resolveCategory(string $komposisi, string $jenisKomposisi): ?ItemCategory
    {
        $label = trim($komposisi.' '.$jenisKomposisi);

        if ($label === '') {
            return null;
        }

        $code = 'OCIA_'.Str::of($label)->upper()->replaceMatches('/[^A-Z0-9]+/', '_')->trim('_')->limit(28, '')->toString();

        return ItemCategory::withoutGlobalScopes()->updateOrCreate(
            ['tenant_id' => $this->tenantId, 'code' => $code],
            ['name' => 'OCIA — '.Str::title(strtolower($label)), 'status' => 'ACTIVE', 'is_active' => true]
        );
    }
}
