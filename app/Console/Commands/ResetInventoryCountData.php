<?php

namespace App\Console\Commands;

use App\Modules\Core\Models\Tenant;
use App\Modules\Operations\Models\InventoryCountDocument;
use App\Modules\Operations\Models\InventoryCountDocumentRevision;
use App\Modules\Operations\Models\InventoryCountLine;
use App\Modules\Operations\Models\InventoryCountMovement;
use App\Modules\Operations\Models\InventoryCountOpeningBalance;
use App\Modules\Operations\Models\InventoryCountTemplate;
use App\Modules\Operations\Models\InventoryCountTemplateItem;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Hapus BERSIH semua data modul Inventory Simple (template, template item,
 * dokumen, baris, riwayat revisi, movement, saldo awal) untuk 1 tenant --
 * supaya bisa "mulai dari nol" (mis. sebelum mengajari staf operasional
 * sungguhan, data uji coba sebelumnya tidak ikut terlihat).
 *
 * HANYA menyentuh 7 tabel inventory_count_* -- TIDAK PERNAH menyentuh
 * stock_balances/stock_mutations atau tabel Opname biasa (OpnameSession/
 * OpnameItem), karena modul ini memang sama sekali tidak pernah menulis ke
 * sana (murni observasi fisik, belum mengubah saldo stok sistem).
 */
class ResetInventoryCountData extends Command
{
    protected $signature = 'inventory-count:reset {--tenant= : Kode tenant, wajib diisi kalau lebih dari 1 tenant}';

    protected $description = 'Delete ALL Inventory Simple data (templates, documents, lines, etc.) for a tenant -- does NOT touch stock ledger or regular Opname data';

    public function handle(): int
    {
        $tenantCode = $this->option('tenant');

        if ($tenantCode) {
            $tenant = Tenant::query()->where('code', strtoupper((string) $tenantCode))->first();
            if (! $tenant) {
                $this->error("Tenant dengan kode '{$tenantCode}' tidak ditemukan.");

                return self::FAILURE;
            }
        } else {
            $count = Tenant::query()->count();
            if ($count !== 1) {
                $this->error("Ada {$count} tenant -- wajib tentukan --tenant=KODE.");

                return self::FAILURE;
            }
            $tenant = Tenant::query()->firstOrFail();
        }

        $tenantId = (int) $tenant->id;

        $counts = [
            'templates' => InventoryCountTemplate::query()->where('tenant_id', $tenantId)->count(),
            'template_items' => InventoryCountTemplateItem::query()->where('tenant_id', $tenantId)->count(),
            'documents' => InventoryCountDocument::query()->where('tenant_id', $tenantId)->count(),
            'lines' => InventoryCountLine::query()->where('tenant_id', $tenantId)->count(),
            'revisions' => InventoryCountDocumentRevision::query()->where('tenant_id', $tenantId)->count(),
            'movements' => InventoryCountMovement::query()->where('tenant_id', $tenantId)->count(),
            'opening_balances' => InventoryCountOpeningBalance::query()->where('tenant_id', $tenantId)->count(),
        ];

        $total = array_sum($counts);

        if ($total === 0) {
            $this->info("Tenant '{$tenant->code}': tidak ada data Inventory Simple sama sekali, tidak ada yang dihapus.");

            return self::SUCCESS;
        }

        $this->warn("Akan menghapus data Inventory Simple untuk tenant '{$tenant->code}':");
        foreach ($counts as $label => $n) {
            if ($n > 0) {
                $this->line("  - {$label}: {$n}");
            }
        }
        $this->warn('TIDAK menyentuh saldo stok (stock_balances/stock_mutations) atau data Opname biasa sama sekali.');

        if (! $this->confirm('Lanjutkan hapus semua data di atas?', false)) {
            $this->info('Dibatalkan, tidak ada yang dihapus.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($tenantId): void {
            InventoryCountLine::query()->where('tenant_id', $tenantId)->delete();
            InventoryCountDocumentRevision::query()->where('tenant_id', $tenantId)->delete();
            InventoryCountDocument::query()->where('tenant_id', $tenantId)->delete();
            InventoryCountMovement::query()->where('tenant_id', $tenantId)->delete();
            InventoryCountOpeningBalance::query()->where('tenant_id', $tenantId)->delete();
            InventoryCountTemplateItem::query()->where('tenant_id', $tenantId)->delete();
            InventoryCountTemplate::query()->where('tenant_id', $tenantId)->delete();
        });

        $this->info("Selesai -- semua data Inventory Simple tenant '{$tenant->code}' sudah dihapus bersih.");

        return self::SUCCESS;
    }
}
