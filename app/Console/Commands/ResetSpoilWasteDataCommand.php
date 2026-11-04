<?php

namespace App\Console\Commands;

use App\Modules\Core\Models\Tenant;
use App\Modules\Operations\Models\SpoilWaste;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Perintah sekali-pakai untuk menghapus SEMUA data Spoil & Waste testing
 * milik 1 tenant -- SEBELUM mulai input data real. Beda dengan sekadar
 * `DELETE FROM spoil_wastes`: untuk spoil yang sudah APPROVED (sudah
 * memotong stok lewat stock_mutations), saldo di stock_balances
 * dikembalikan dulu (reversal) sebelum mutasi & record-nya dihapus --
 * supaya qty_on_hand tidak ikut jadi salah/kurang setelah dihapus.
 *
 * Cara pakai:
 *   php artisan sifobi:reset-spoil-wastes MKO --dry-run
 *   php artisan sifobi:reset-spoil-wastes MKO
 */
class ResetSpoilWasteDataCommand extends Command
{
    protected $signature = 'sifobi:reset-spoil-wastes
        {tenant_code : Kode tenant yang datanya mau dibersihkan, mis. MKO}
        {--dry-run : Cuma tampilkan preview, tidak menghapus apa pun}
        {--yes : Lewati konfirmasi interaktif}';

    protected $description = 'Hapus semua data Spoil & Waste testing milik 1 tenant, sekalian kembalikan (reversal) saldo stok yang sudah terpotong -- master data & modul lain tidak disentuh.';

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

        $spoils = DB::table('spoil_wastes')->where('tenant_id', $tenantId)->get(['id', 'mutation_id', 'status']);

        if ($spoils->isEmpty()) {
            $this->info('Tidak ada data Spoil & Waste untuk tenant ini. Tidak ada yang dihapus.');

            return self::SUCCESS;
        }

        $spoilIds = $spoils->pluck('id');

        // Jangan cuma pakai spoil_wastes.mutation_id (cuma menunjuk mutasi
        // approve() yang pertama) -- kalau sebuah spoil yang sudah approved
        // lalu DITOLAK, SpoilWasteService::reject() membuat mutasi
        // VOID_REVERSAL terpisah yang reference_type/reference_id-nya
        // JUGA diset ke spoil yang sama (lihat SpoilWasteService.php:212-218),
        // tapi TIDAK tercatat balik ke kolom mutation_id. Supaya reversal
        // saldo dihitung benar untuk kedua jenis mutasi itu sekaligus,
        // dicari lewat reference_type+reference_id, bukan mutation_id.
        $mutations = DB::table('stock_mutations')
            ->where('reference_type', SpoilWaste::class)
            ->whereIn('reference_id', $spoilIds)
            ->get(['id', 'source_mutation_id', 'reference_id', 'outlet_id', 'item_id', 'stock_target', 'qty_change']);

        $mutationIds = $mutations->pluck('id');

        // Jaga-jaga: VOID_REVERSAL tidak bisa di-void lagi (lihat
        // StockLedgerService::voidMutation(), ditolak eksplisit), jadi
        // seharusnya tidak ada mutasi LAIN di luar set ini yang menunjuk
        // balik ke salah satu mutasi di atas lewat source_mutation_id.
        // Dicek di sini supaya kalau ternyata ada (kasus tak terduga),
        // errornya jelas dan tidak menabrak FK restrictOnDelete mentah-mentah.
        $blockedBy = DB::table('stock_mutations')
            ->whereIn('source_mutation_id', $mutationIds)
            ->whereNotIn('id', $mutationIds)
            ->count();

        if ($blockedBy > 0) {
            $this->error("Ada {$blockedBy} mutasi stok lain yang masih bergantung pada mutasi spoil ini (di luar pasangan approve/void yang sudah ditangani). Tidak bisa dihapus otomatis -- butuh pengecekan manual.");

            return self::FAILURE;
        }

        $adjustments = $mutations
            ->groupBy(fn ($m) => $m->outlet_id.'|'.$m->item_id.'|'.$m->stock_target)
            ->map(fn ($group) => [
                'outlet_id' => $group->first()->outlet_id,
                'item_id' => $group->first()->item_id,
                'stock_target' => $group->first()->stock_target,
                'sum_qty_change' => $group->reduce(fn ($carry, $m) => bcadd($carry, (string) $m->qty_change, 6), '0.000000'),
            ])
            // Spoil yang approved lalu ditolak nett-nya 0 (mutasi asli +
            // VOID_REVERSAL saling meniadakan) -- saldonya sudah benar
            // apa adanya, tidak perlu disentuh lagi.
            ->filter(fn ($adj) => bccomp($adj['sum_qty_change'], '0.000000', 6) !== 0);

        $touchedSpoilIds = $mutations->pluck('reference_id')->unique();

        $this->table(
            ['Status', 'Jumlah'],
            [
                ['Total record Spoil & Waste', $spoils->count()],
                ['Pernah menyentuh ledger stok (approved, termasuk yang lalu ditolak)', $touchedSpoilIds->count()],
                ['Tidak pernah menyentuh ledger (masih pending/ditolak sebelum approve)', $spoils->count() - $touchedSpoilIds->count()],
                ['Mutasi stok yang akan dihapus (approve + void reversal)', $mutations->count()],
                ['Baris stock_balances yang akan disesuaikan', $adjustments->count()],
            ]
        );

        if ($this->option('dry-run')) {
            $this->warn('Dry-run: belum ada yang dihapus/disesuaikan.');

            return self::SUCCESS;
        }

        if (! $this->option('yes')) {
            $this->warn("PERINGATAN: akan menghapus permanen {$spoils->count()} record Spoil & Waste tenant {$tenantCode}, termasuk mengembalikan saldo stok yang sudah terpotong oleh spoil yang sudah approved.");
            $confirm = $this->ask("Ketik ulang kode tenant \"{$tenantCode}\" untuk konfirmasi");

            if ($confirm !== $tenantCode) {
                $this->error('Konfirmasi tidak cocok. Dibatalkan, tidak ada yang dihapus.');

                return self::FAILURE;
            }
        }

        DB::transaction(function () use ($tenantId, $mutations, $adjustments): void {
            foreach ($adjustments as $adj) {
                $balance = DB::table('stock_balances')
                    ->where('tenant_id', $tenantId)
                    ->where('outlet_id', $adj['outlet_id'])
                    ->where('item_id', $adj['item_id'])
                    ->where('stock_target', $adj['stock_target'])
                    ->lockForUpdate()
                    ->first();

                if (! $balance) {
                    continue;
                }

                // qty_change spoil selalu negatif (pengurangan stok) --
                // dikembalikan dengan DIKURANGI sum negatifnya, bukan
                // ditambah, supaya hasilnya naik balik ke nilai sebelum
                // spoil ini pernah tercatat. avg_cost TIDAK berubah
                // (lihat StockLedgerService::averageCostAfterMove -- outgoing
                // move tidak pernah mengubah avg_cost), jadi total_value
                // cukup dihitung ulang dari qty baru x avg_cost lama.
                $newQty = bcsub((string) $balance->qty_on_hand, $adj['sum_qty_change'], 6);
                $newTotalValue = bcmul($newQty, (string) $balance->avg_cost, 4);

                DB::table('stock_balances')->where('id', $balance->id)->update([
                    'qty_on_hand' => $newQty,
                    'total_value' => $newTotalValue,
                    'updated_at' => now(),
                ]);
            }

            // Anak (VOID_REVERSAL, source_mutation_id terisi) wajib dihapus
            // SEBELUM induknya (mutasi asli yang jadi source-nya) --
            // kalau dihapus dalam 1 statement sekaligus, urutan internal
            // DB tidak terjamin dan bisa menabrak FK stock_mutations.
            // source_mutation_id (restrictOnDelete).
            [$children, $parents] = $mutations->partition(fn ($m) => $m->source_mutation_id !== null);

            if ($children->isNotEmpty()) {
                DB::table('stock_mutations')->whereIn('id', $children->pluck('id'))->delete();
            }

            if ($parents->isNotEmpty()) {
                DB::table('stock_mutations')->whereIn('id', $parents->pluck('id'))->delete();
            }

            DB::table('spoil_wastes')->where('tenant_id', $tenantId)->delete();
        });

        $this->newLine();
        $this->info("Selesai. {$spoils->count()} record Spoil & Waste tenant {$tenantCode} sudah dihapus, saldo stok yang sempat terpotong sudah dikembalikan.");

        return self::SUCCESS;
    }
}
