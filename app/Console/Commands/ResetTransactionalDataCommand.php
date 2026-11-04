<?php

namespace App\Console\Commands;

use App\Modules\Core\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Perintah sekali-pakai untuk membersihkan SEMUA data transaksi testing
 * (PO, Penerimaan Barang, Opname, Spoil, Stock Transfer, Open Stock,
 * ledger/saldo stok, POS, dst) milik 1 tenant SEBELUM mulai input data
 * real -- supaya data real tidak bercampur dengan hasil uji coba.
 *
 * Master data (item, outlet, brand, user, department, kategori,
 * integrasi, resep/menu, template opname) TIDAK disentuh sama sekali.
 *
 * Cara pakai:
 *   php artisan sifobi:reset-transactions MKO --dry-run   (lihat jumlah dulu, tidak menghapus)
 *   php artisan sifobi:reset-transactions MKO             (hapus, minta konfirmasi ketik ulang kode tenant)
 *   php artisan sifobi:reset-transactions MKO --yes        (hapus tanpa prompt, buat dipakai non-interaktif)
 */
class ResetTransactionalDataCommand extends Command
{
    protected $signature = 'sifobi:reset-transactions
        {tenant_code : Kode tenant yang datanya mau dibersihkan, mis. MKO}
        {--dry-run : Cuma tampilkan jumlah baris yang akan terhapus, tidak menghapus apa pun}
        {--yes : Lewati konfirmasi interaktif (buat dipakai sekali lewat SSH non-interaktif)}';

    protected $description = 'Hapus semua data transaksi testing (PO, GR, Opname, Spoil, ledger stok, POS, dll) milik 1 tenant -- master data tidak disentuh.';

    /**
     * Urutan SENGAJA anak dulu baru induk, supaya aman walau FK constraint
     * tidak pakai cascadeOnDelete. Semua tabel di sini punya kolom
     * tenant_id langsung KECUALI stock_transfer_items (ditangani khusus
     * lewat join ke stock_transfers, lihat deleteStockTransferItems()).
     */
    private const TABLES_IN_ORDER = [
        'pos_payments',
        'pos_order_items',
        'pos_orders',
        'pos_shifts',
        'member_point_transactions',
        'hpp_calculations',
        'document_capture_items',
        'document_captures',
        'purchase_order_shipments',
        'purchase_order_approval_events',
        'purchase_order_items',
        'purchase_orders',
        'goods_receipt_items',
        'goods_receipts',
        'opname_items',
        'opname_sessions',
        'open_stocks',
        'spoil_wastes',
        'inventory_count_movements',
        'inventory_count_lines',
        'inventory_count_document_revisions',
        'inventory_count_documents',
        'monthly_import_runs',
        'stock_mutations',
        'stock_balances',
    ];

    public function handle(): int
    {
        $tenantCode = strtoupper((string) $this->argument('tenant_code'));
        $tenant = Tenant::query()->where('code', $tenantCode)->first();

        if (! $tenant) {
            $this->error("Tenant dengan kode \"{$tenantCode}\" tidak ditemukan.");

            return self::FAILURE;
        }

        $tenantId = (int) $tenant->id;

        $this->info("Tenant: {$tenant->name} ({$tenantCode}), ID {$tenantId}");
        $this->newLine();

        $counts = $this->countRows($tenantId);
        $this->table(['Tabel', 'Jumlah baris'], collect($counts)->map(fn ($n, $t) => [$t, $n])->values());

        $total = array_sum($counts);

        if ($total === 0) {
            $this->info('Tidak ada data transaksi untuk tenant ini. Tidak ada yang dihapus.');

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->warn("Dry-run: total {$total} baris AKAN terhapus kalau dijalankan tanpa --dry-run. Belum ada yang dihapus.");

            return self::SUCCESS;
        }

        if (! $this->option('yes')) {
            $this->warn("PERINGATAN: akan menghapus permanen total {$total} baris transaksi (PO, Penerimaan Barang, Opname, Spoil, Stock Transfer, Open Stock, ledger/saldo stok, POS, dll) milik tenant {$tenantCode}.");
            $this->warn('Master data (item, outlet, user, resep, dll) TIDAK ikut terhapus.');
            $confirm = $this->ask("Ketik ulang kode tenant \"{$tenantCode}\" untuk konfirmasi");

            if ($confirm !== $tenantCode) {
                $this->error('Konfirmasi tidak cocok. Dibatalkan, tidak ada yang dihapus.');

                return self::FAILURE;
            }
        }

        DB::transaction(function () use ($tenantId): void {
            foreach (self::TABLES_IN_ORDER as $table) {
                if (! Schema::hasTable($table)) {
                    continue;
                }

                DB::table($table)->where('tenant_id', $tenantId)->delete();
            }

            $this->deleteStockTransferItems($tenantId);
        });

        $this->newLine();
        $this->info("Selesai. Total {$total} baris transaksi tenant {$tenantCode} sudah dihapus. Siap input data real.");

        return self::SUCCESS;
    }

    /**
     * @return array<string, int>
     */
    private function countRows(int $tenantId): array
    {
        $counts = [];

        foreach (self::TABLES_IN_ORDER as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $counts[$table] = DB::table($table)->where('tenant_id', $tenantId)->count();
        }

        if (Schema::hasTable('stock_transfers')) {
            $counts['stock_transfers'] = DB::table('stock_transfers')->where('tenant_id', $tenantId)->count();
        }

        if (Schema::hasTable('stock_transfer_items') && Schema::hasTable('stock_transfers')) {
            $counts['stock_transfer_items'] = DB::table('stock_transfer_items')
                ->whereIn('stock_transfer_id', DB::table('stock_transfers')->where('tenant_id', $tenantId)->pluck('id'))
                ->count();
        }

        return $counts;
    }

    private function deleteStockTransferItems(int $tenantId): void
    {
        if (! Schema::hasTable('stock_transfer_items') || ! Schema::hasTable('stock_transfers')) {
            return;
        }

        DB::table('stock_transfer_items')
            ->whereIn('stock_transfer_id', DB::table('stock_transfers')->where('tenant_id', $tenantId)->pluck('id'))
            ->delete();

        DB::table('stock_transfers')->where('tenant_id', $tenantId)->delete();
    }
}
