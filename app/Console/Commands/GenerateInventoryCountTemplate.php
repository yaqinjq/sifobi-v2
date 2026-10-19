<?php

namespace App\Console\Commands;

use App\Modules\Core\Models\Department;
use App\Modules\Core\Models\Tenant;
use App\Modules\Inventory\Models\Item;
use App\Modules\Operations\Models\InventoryCountTemplate;
use App\Modules\Operations\Models\InventoryCountTemplateItem;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Bikin/perbarui InventoryCountTemplate untuk 1 departemen (atau SEMUA
 * departemen tenant lewat --all), pakai item ASLI yang SUDAH terdaftar ke
 * departemen itu lewat jalur yang SAMA dengan Opname biasa
 * (primary_department_id / relasi departments many-to-many, is_active,
 * track_stock) -- BUKAN data sintetis, BUKAN mapping manual baru.
 *
 * SENGAJA tidak set requires_conversion / location_units override sama
 * sekali (1 lokasi generik "Gudang", unit langsung dari item) supaya
 * config_valid SELALU true untuk setiap item -- menghindari kelas masalah
 * "Onde-Onde" (item perlu rasio konversi yang belum dikonfigurasi) sama
 * sekali, bukan menyelesaikannya. Kalau nanti mau tambah lokasi/konversi
 * per item, itu penyesuaian terpisah lewat UI/command lain.
 *
 * Idempoten: dipanggil ulang untuk departemen yang sama hanya menambah item
 * yang belum ada di template (tidak membuat template duplikat, tidak
 * menduplikasi baris item).
 */
class GenerateInventoryCountTemplate extends Command
{
    protected $signature = 'inventory-count:generate-template {department_code?} {--all : Proses SEMUA departemen tenant, bukan cuma 1} {--tenant= : Kode tenant (wajib kalau pakai --all dan ada >1 tenant)}';

    protected $description = 'Generate/update Inventory Simple template(s) using real, already-assigned items (safe for production)';

    public function handle(): int
    {
        if ($this->option('all')) {
            return $this->handleAll();
        }

        $code = $this->argument('department_code');

        if (! $code) {
            $this->error('Isi kode departemen, atau pakai --all untuk semua departemen.');

            return self::FAILURE;
        }

        $department = Department::query()->where('code', strtoupper((string) $code))->first();

        if (! $department) {
            $this->error("Departemen dengan kode '{$code}' tidak ditemukan.");

            return self::FAILURE;
        }

        [$added, $total] = $this->generateForDepartment($department);

        if ($total === 0) {
            $this->error("Tidak ada item aktif (track_stock) yang terdaftar ke departemen '{$department->code}'. Tidak ada template dibuat.");

            return self::FAILURE;
        }

        $this->info("{$department->name}: {$added} item baru ditambahkan, total {$total} item.");

        return self::SUCCESS;
    }

    private function handleAll(): int
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
                $this->error("Ada {$count} tenant -- wajib tentukan --tenant=KODE kalau pakai --all.");

                return self::FAILURE;
            }
            $tenant = Tenant::query()->firstOrFail();
        }

        $departments = Department::query()->where('tenant_id', $tenant->id)->orderBy('name')->get();

        if ($departments->isEmpty()) {
            $this->error("Tenant '{$tenant->code}' tidak punya departemen sama sekali.");

            return self::FAILURE;
        }

        $anyCreated = false;

        foreach ($departments as $department) {
            [$added, $total] = $this->generateForDepartment($department);

            if ($total === 0) {
                $this->line("{$department->name} ({$department->code}): dilewati, tidak ada item aktif terdaftar.");

                continue;
            }

            $anyCreated = true;
            $this->info("{$department->name} ({$department->code}): {$added} item baru ditambahkan, total {$total} item.");
        }

        if (! $anyCreated) {
            $this->error('Tidak ada satu pun departemen yang punya item aktif -- tidak ada template dibuat.');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * @return array{0: int, 1: int} [item baru ditambahkan, total item di template]
     */
    private function generateForDepartment(Department $department): array
    {
        $tenantId = (int) $department->tenant_id;

        $items = Item::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->where('track_stock', true)
            ->where(function ($q) use ($department): void {
                $q->where('primary_department_id', $department->id)
                    ->orWhereHas('departments', fn ($dq) => $dq->where('departments.id', $department->id));
            })
            ->orderBy('name')
            ->get();

        if ($items->isEmpty()) {
            return [0, 0];
        }

        return DB::transaction(function () use ($tenantId, $department, $items): array {
            $template = InventoryCountTemplate::query()->firstOrCreate(
                ['tenant_id' => $tenantId, 'department_id' => $department->id, 'frequency' => InventoryCountTemplate::FREQUENCY_DAILY],
                [
                    'code' => $department->code.'_DAILY',
                    'name' => $department->name.' - Harian',
                    'locations' => ['Gudang'],
                    'is_generic' => false,
                    'is_configured' => true,
                    'status' => InventoryCountTemplate::STATUS_ACTIVE,
                ]
            );

            $existingItemIds = $template->items()->pluck('item_id')->all();
            $sortOrder = $template->items()->max('sort_order') ?? 0;
            $added = 0;

            foreach ($items as $item) {
                if (in_array($item->id, $existingItemIds, true)) {
                    continue;
                }

                InventoryCountTemplateItem::query()->create([
                    'tenant_id' => $tenantId,
                    'template_id' => $template->id,
                    'item_id' => $item->id,
                    'sort_order' => ++$sortOrder,
                    'locations' => ['Gudang'],
                    'requires_conversion' => false,
                    'is_active' => true,
                ]);
                $added++;
            }

            return [$added, $template->items()->count()];
        });
    }
}
