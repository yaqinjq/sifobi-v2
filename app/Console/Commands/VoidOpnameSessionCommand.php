<?php

namespace App\Console\Commands;

use App\Modules\Core\Models\Department;
use App\Modules\Core\Models\Outlet;
use App\Modules\Core\Models\Tenant;
use App\Modules\Operations\Models\OpnameSession;
use App\Services\StockLedgerService;
use Illuminate\Console\Command;

/**
 * Perintah sekali-pakai untuk membatalkan 1 sesi Opname spesifik (misal
 * tanggal yang tidak sengaja ikut ter-apply saat Import Bulanan) --
 * MIRIP sifobi:reset-spoil-wastes: kalau sesi sudah PROCESSED (sudah
 * memotong/menyesuaikan stok lewat stock_mutations), saldo stok
 * dikembalikan dulu lewat VOID_REVERSAL (StockLedgerService::voidMutation(),
 * TYPE-AGNOSTIC -- berlaku utk mutation_type apa pun, termasuk
 * DAILY_OPNAME_ADJ/MONTHLY_OPNAME_ADJ) SEBELUM sesi & item-nya dihapus
 * permanen -- supaya qty_on_hand tidak ikut jadi salah/kurang setelah
 * dihapus.
 *
 * Cara pakai:
 *   php artisan sifobi:void-opname-session MKO "Ah Pek Tunjungan" Kitchen 2026-11-30 --dry-run
 *   php artisan sifobi:void-opname-session MKO "Ah Pek Tunjungan" Kitchen 2026-11-30
 */
class VoidOpnameSessionCommand extends Command
{
    protected $signature = 'sifobi:void-opname-session
        {tenant_code : Kode tenant, mis. MKO}
        {outlet : Nama atau kode outlet, mis. "Ah Pek Tunjungan"}
        {department : Nama atau kode departemen, mis. Kitchen}
        {date : Tanggal opname, format YYYY-MM-DD}
        {--dry-run : Cuma tampilkan preview, tidak menghapus/menyesuaikan apa pun}
        {--yes : Lewati konfirmasi interaktif}';

    protected $description = 'Batalkan 1 sesi Opname spesifik (outlet+departemen+tanggal) -- sekalian kembalikan (reversal) saldo stok kalau sesinya sudah PROCESSED.';

    public function handle(StockLedgerService $stockLedgerService): int
    {
        $tenantCode = strtoupper((string) $this->argument('tenant_code'));
        $tenant = Tenant::query()->where('code', $tenantCode)->first();

        if (! $tenant) {
            $this->error("Tenant dengan kode \"{$tenantCode}\" tidak ditemukan.");

            return self::FAILURE;
        }

        $outletInput = (string) $this->argument('outlet');
        $outlet = Outlet::query()->where('tenant_id', $tenant->id)
            ->where(fn ($q) => $q->where('code', $outletInput)->orWhere('name', $outletInput))
            ->first();

        if (! $outlet) {
            $this->error("Outlet \"{$outletInput}\" tidak ditemukan untuk tenant {$tenantCode}.");

            return self::FAILURE;
        }

        $departmentInput = (string) $this->argument('department');
        $department = Department::query()->where('tenant_id', $tenant->id)
            ->where(fn ($q) => $q->where('code', $departmentInput)->orWhere('name', $departmentInput))
            ->first();

        if (! $department) {
            $this->error("Departemen \"{$departmentInput}\" tidak ditemukan untuk tenant {$tenantCode}.");

            return self::FAILURE;
        }

        $date = (string) $this->argument('date');

        $session = OpnameSession::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('outlet_id', $outlet->id)
            ->where('department_id', $department->id)
            ->whereDate('opname_date', $date)
            ->with('items')
            ->first();

        if (! $session) {
            $this->error("Tidak ada sesi Opname untuk {$outlet->name} / {$department->name} / {$date}.");

            return self::FAILURE;
        }

        $mutationIds = $session->items->pluck('mutation_id')->filter()->values();

        $this->table(
            ['Info', 'Nilai'],
            [
                ['Outlet', $outlet->name],
                ['Departemen', $department->name],
                ['Tanggal', $date],
                ['Status sesi', $session->status],
                ['Jumlah item di sesi', $session->items->count()],
                ['Mutasi stok yang akan di-void', $mutationIds->count()],
            ]
        );

        if ($this->option('dry-run')) {
            $this->warn('Dry-run: belum ada yang dihapus/disesuaikan.');

            return self::SUCCESS;
        }

        if (! $this->option('yes')) {
            $this->warn('PERINGATAN: akan menghapus permanen sesi Opname ini beserta seluruh itemnya, termasuk mengembalikan saldo stok yang sudah disesuaikan (kalau statusnya PROCESSED).');
            $confirm = $this->ask('Ketik ulang tanggalnya (YYYY-MM-DD) untuk konfirmasi');

            if ($confirm !== $date) {
                $this->error('Konfirmasi tidak cocok. Dibatalkan, tidak ada yang dihapus.');

                return self::FAILURE;
            }
        }

        foreach ($mutationIds as $mutationId) {
            $stockLedgerService->voidMutation((int) $mutationId, [
                'void_reason' => "Sesi Opname {$date} ({$outlet->name}/{$department->name}) dibatalkan via sifobi:void-opname-session.",
            ]);
        }

        $session->delete();

        $this->newLine();
        $this->info("Selesai. Sesi Opname {$outlet->name} / {$department->name} / {$date} sudah dihapus, saldo stok yang sempat disesuaikan sudah dikembalikan.");

        return self::SUCCESS;
    }
}
