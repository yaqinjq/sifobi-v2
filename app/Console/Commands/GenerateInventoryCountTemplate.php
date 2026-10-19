<?php

namespace App\Console\Commands;

use App\Modules\Core\Models\Department;
use App\Modules\Inventory\Models\Item;
use App\Modules\Operations\Models\InventoryCountTemplate;
use App\Modules\Operations\Models\InventoryCountTemplateItem;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Bikin/perbarui 1 InventoryCountTemplate untuk 1 departemen, pakai item
 * ASLI yang SUDAH terdaftar ke departemen itu lewat jalur yang SAMA dengan
 * Opname biasa (primary_department_id / relasi departments many-to-many,
 * is_active, track_stock) -- BUKAN data sintetis, BUKAN mapping manual baru.
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
    protected $signature = 'inventory-count:generate-template {department_code}';

    protected $description = 'Generate/update an Inventory Simple template for a department using real, already-assigned items (safe for production)';

    public function handle(): int
    {
        $code = strtoupper((string) $this->argument('department_code'));

        $department = Department::query()->where('code', $code)->first();

        if (! $department) {
            $this->error("Departemen dengan kode '{$code}' tidak ditemukan.");

            return self::FAILURE;
        }

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
            $this->error("Tidak ada item aktif (track_stock) yang terdaftar ke departemen '{$code}'. Tidak ada template dibuat.");

            return self::FAILURE;
        }

        DB::transaction(function () use ($tenantId, $department, $code, $items): void {
            $template = InventoryCountTemplate::query()->firstOrCreate(
                ['tenant_id' => $tenantId, 'department_id' => $department->id, 'frequency' => InventoryCountTemplate::FREQUENCY_DAILY],
                [
                    'code' => $code.'_DAILY',
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

            $this->info("Template '{$template->name}' (ID {$template->id}): {$added} item baru ditambahkan, total ".$template->items()->count().' item.');
        });

        return self::SUCCESS;
    }
}
