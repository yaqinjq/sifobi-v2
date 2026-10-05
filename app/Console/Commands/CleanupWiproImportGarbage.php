<?php

namespace App\Console\Commands;

use App\Modules\Inventory\Models\Item;
use App\Modules\Inventory\Models\ItemCategory;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Bersihkan item & kategori sampah yang dibuat WiproCatalogImport SEBELUM
 * fix kolom header diterapkan -- waktu itu kolom kebaca salah posisi, jadi
 * SKU item jadi nilai Product_Code ("KWIP0546" dst), Nama item jadi nilai
 * SKU asli, dan Kategori-nya jadi nilai Is_Active ("0"/"1"), menghasilkan
 * kategori ganjil "Wipro — 0" / "Wipro — 1" yang tidak mungkin dibuat oleh
 * import yang benar (tidak ada kategori Wipro asli yang namanya cuma angka).
 *
 * Default DRY-RUN (cuma melaporkan) -- pakai --force untuk benar-benar
 * menghapus. Item yang masih dipakai di PO/GR/Resep/dll TIDAK akan terhapus
 * (foreign key di tabel terkait semuanya RESTRICT ON DELETE) -- akan
 * dilaporkan sebagai "dilewati" supaya bisa ditangani manual.
 */
class CleanupWiproImportGarbage extends Command
{
    protected $signature = 'wipro:cleanup-garbage
                            {tenant_id : Tenant ID yang mau dibersihkan}
                            {--force : Benar-benar hapus (default cuma preview)}';

    protected $description = 'Hapus item & kategori sampah dari upload Katalog Wipro sebelum fix kolom header (kategori "Wipro — <angka>")';

    public function handle(): int
    {
        $tenantId = (int) $this->argument('tenant_id');
        $force = (bool) $this->option('force');

        $garbageCategories = ItemCategory::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('name', 'like', 'Wipro — %')
            ->get()
            ->filter(fn (ItemCategory $category): bool => (bool) preg_match('/^Wipro — \d+$/u', $category->name));

        if ($garbageCategories->isEmpty()) {
            $this->info('Tidak ada kategori sampah ("Wipro — <angka>") ditemukan. Tidak ada yang perlu dibersihkan.');

            return self::SUCCESS;
        }

        $this->warn($force ? '=== MODE HAPUS SUNGGUHAN ===' : '=== DRY RUN — tidak ada yang dihapus, tambahkan --force untuk eksekusi ===');

        $deletedItems = 0;
        $skippedItems = 0;

        foreach ($garbageCategories as $category) {
            $items = Item::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('item_category_id', $category->id)
                ->get();

            $this->line("Kategori sampah \"{$category->name}\" (id {$category->id}): {$items->count()} item");

            foreach ($items as $item) {
                $this->line("  - #{$item->id} sku={$item->canonical_sku} nama=\"{$item->name}\"");

                if (! $force) {
                    continue;
                }

                try {
                    DB::transaction(fn () => $item->delete());
                    $deletedItems++;
                } catch (QueryException $e) {
                    $this->error("    dilewati -- masih dipakai di tabel lain (PO/GR/Resep/dll): {$e->getMessage()}");
                    $skippedItems++;
                }
            }

            if ($force) {
                $remaining = Item::withoutGlobalScopes()->where('item_category_id', $category->id)->count();

                if ($remaining === 0) {
                    $category->delete();
                    $this->info("  Kategori \"{$category->name}\" ikut dihapus (sudah kosong).");
                } else {
                    $this->warn("  Kategori \"{$category->name}\" DIBIARKAN -- masih ada {$remaining} item yang dilewati di dalamnya.");
                }
            }
        }

        if ($force) {
            $this->info("Selesai. Terhapus: {$deletedItems}, dilewati (masih dipakai): {$skippedItems}.");
        } else {
            $this->info('Dry run selesai. Jalankan ulang dengan --force untuk benar-benar menghapus.');
        }

        return self::SUCCESS;
    }
}
