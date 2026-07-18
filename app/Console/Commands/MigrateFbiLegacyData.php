<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class MigrateFbiLegacyData extends Command
{
    protected $signature = 'migrate:fbi-legacy
        {--dry-run : Preview tanpa eksekusi ke DB}
        {--only= : Jalankan hanya 1 tahap: units|brands|outlets|departments|jenises|categories|items|item-outlets|open-stocks|users}
        {--fresh-items : Hapus items dummy sebelum import (HATI-HATI!)}';

    protected $description = 'Migrasi data dari database FBI lama ke SIFOBI v2';

    private bool $isDryRun = false;

    private int $tenantId = 1;

    private int $defaultLegalEntityId = 1;

    /** @var array<int, int> */
    private array $unitMap = [];

    /** @var array<int, int> */
    private array $groupMap = [];

    /** @var array<int, int> */
    private array $brandMap = [];

    /** @var array<int, int> */
    private array $outletMap = [];

    /** @var array<int, int> */
    private array $deptMap = [];

    /** @var array<int, int> */
    private array $jenisMap = [];

    /** @var array<int, int> */
    private array $categoryMap = [];

    /** @var array<int, int> */
    private array $itemMap = [];

    public function handle(): int
    {
        $this->isDryRun = (bool) $this->option('dry-run');
        $only = $this->option('only');

        $steps = [
            'units' => 'migrateUnits',
            'brands' => 'migrateBrands',
            'outlets' => 'migrateOutlets',
            'departments' => 'migrateDepartments',
            'jenises' => 'migrateJenises',
            'categories' => 'migrateCategories',
            'items' => 'migrateItems',
            'item-outlets' => 'migrateItemOutlets',
            'open-stocks' => 'migrateOpenStocks',
            'users' => 'migrateUsers',
        ];

        if ($only && ! array_key_exists($only, $steps)) {
            $this->error("Tahap '{$only}' tidak dikenal.");
            $this->line('Pilihan valid: '.implode('|', array_keys($steps)));

            return self::FAILURE;
        }

        if ($this->isDryRun) {
            $this->warn('=== DRY RUN MODE: tidak ada data yang diubah ===');
        }

        try {
            $this->legacy()->statement('SELECT 1');
            $this->info('Koneksi ke database FBI lama: OK');
            $this->line('DB SIFOBI target: '.DB::connection()->getDatabaseName());
            $this->line('DB FBI legacy: '.$this->legacy()->getDatabaseName());
        } catch (Throwable $e) {
            $this->error('Gagal konek ke database FBI: '.$e->getMessage());
            $this->error('Pastikan FBI_DB_DATABASE, FBI_DB_USERNAME, FBI_DB_PASSWORD sudah diset di .env');

            return self::FAILURE;
        }

        foreach ($steps as $step => $method) {
            if ($only && $only !== $step) {
                continue;
            }

            $this->newLine();
            $this->info("Tahap: {$step}");

            try {
                $this->{$method}();
            } catch (Throwable $e) {
                $this->error("Error tahap {$step}: ".$e->getMessage());
                Log::error("MigrateFbiLegacyData [{$step}]: ".$e->getMessage(), [
                    'exception' => $e,
                ]);

                if ($only) {
                    return self::FAILURE;
                }
            }
        }

        $this->newLine();
        $this->info('Migrasi selesai.');
        $this->printTargetCounts();

        return self::SUCCESS;
    }

    private function migrateUnits(): void
    {
        if (! $this->legacyTableExists('tbl_data_satuan')) {
            $this->warn('Tabel legacy tbl_data_satuan tidak ditemukan.');

            return;
        }

        $rows = $this->legacy()->table('tbl_data_satuan')->get();
        $stats = $this->emptyStats();

        $this->withTargetTransaction(function () use ($rows, &$stats): void {
            foreach ($rows as $row) {
                try {
                    $legacyId = (int) $this->val($row, 'id_satuan');
                    $legacyCode = $this->normalizeCode($this->val($row, 'kode_satuan', 'NA'), 'NA');
                    $code = $this->unitCodeMap()[$legacyCode] ?? $legacyCode;
                    [$name, $abbr] = $this->unitNameMap()[$legacyCode] ?? [
                        $this->title($this->val($row, 'nama_satuan', $code)),
                        strtolower((string) $this->val($row, 'singkatan', $code)),
                    ];

                    if ($this->isDryRun) {
                        $existing = $this->findTarget('units', [
                            'tenant_id' => $this->tenantId,
                            'code' => $code,
                        ]);
                        $this->unitMap[$legacyId] = $existing?->id ?? $this->fakeId($legacyId);
                        $stats[$existing ? 'skipped' : 'inserted']++;
                        $this->line(($existing ? '  [SKIP] ' : '  [UPSERT] ')."Unit {$code} - {$name}");
                        continue;
                    }

                    $id = $this->upsertGetId('units', [
                        'tenant_id' => $this->tenantId,
                        'code' => $code,
                    ], [
                        'name' => $name,
                        'abbreviation' => $abbr,
                        'decimal_places' => $this->decimalPlacesForUnit($code),
                        'status' => 'ACTIVE',
                    ]);

                    $this->unitMap[$legacyId] = $id;
                    $stats['inserted']++;
                } catch (Throwable $e) {
                    $stats['errors']++;
                    $this->logRowError('units', $row, $e);
                }
            }
        });

        $this->summary('Units', $stats);
    }

    private function migrateBrands(): void
    {
        $stats = $this->emptyStats();

        $this->withTargetTransaction(function () use (&$stats): void {
            if ($this->legacyTableExists('tbl_com_group')) {
                $groups = $this->legacy()->table('tbl_com_group')->get();

                foreach ($groups as $row) {
                    try {
                        $legacyId = (int) $this->val($row, 'id_group', $this->val($row, 'id_com_group', 0));
                        $code = $this->normalizeCode($this->val($row, 'kode_group', 'FBI'));
                        $name = $this->title($this->val($row, 'nama_group', $code));

                        if ($this->isDryRun) {
                            $existing = $this->findTarget('groups', [
                                'tenant_id' => $this->tenantId,
                                'code' => $code,
                            ]);
                            $this->groupMap[$legacyId] = $existing?->id ?? $this->fakeId($legacyId);
                            $this->line(($existing ? '  [SKIP] ' : '  [UPSERT] ')."Group {$code} - {$name}");
                            continue;
                        }

                        $this->groupMap[$legacyId] = $this->upsertGetId('groups', [
                            'tenant_id' => $this->tenantId,
                            'code' => $code,
                        ], [
                            'name' => $name,
                            'status' => 'ACTIVE',
                        ]);
                    } catch (Throwable $e) {
                        $stats['errors']++;
                        $this->logRowError('brands.groups', $row, $e);
                    }
                }
            }

            if (! $this->legacyTableExists('tbl_com_brand')) {
                $this->warn('Tabel legacy tbl_com_brand tidak ditemukan.');

                return;
            }

            $rows = $this->legacy()->table('tbl_com_brand')->get();

            foreach ($rows as $row) {
                try {
                    $legacyId = (int) $this->val($row, 'id_brand');
                    $code = $this->normalizeCode($this->val($row, 'kode_brand', 'BRAND-'.$legacyId));
                    $name = $this->title($this->val($row, 'nama_brand', $code));
                    $legacyGroupId = (int) $this->val($row, 'id_group', $this->val($row, 'id_com_group', 0));
                    $groupId = $this->groupMap[$legacyGroupId] ?? $this->defaultGroupId();

                    if ($this->isDryRun) {
                        $existing = $this->findTarget('brands', [
                            'tenant_id' => $this->tenantId,
                            'code' => $code,
                        ]);
                        $this->brandMap[$legacyId] = $existing?->id ?? $this->fakeId($legacyId);
                        $stats[$existing ? 'skipped' : 'inserted']++;
                        $this->line(($existing ? '  [SKIP] ' : '  [UPSERT] ')."Brand {$code} - {$name}");
                        continue;
                    }

                    $this->brandMap[$legacyId] = $this->upsertGetId('brands', [
                        'tenant_id' => $this->tenantId,
                        'code' => $code,
                    ], [
                        'group_id' => $groupId,
                        'name' => $name,
                        'status' => $this->statusFromLegacy($this->val($row, 'status_brand', $this->val($row, 'statur_brand', 'AKTIF'))),
                    ]);
                    $stats['inserted']++;
                } catch (Throwable $e) {
                    $stats['errors']++;
                    $this->logRowError('brands', $row, $e);
                }
            }
        });

        $this->summary('Brands', $stats);
    }

    private function migrateOutlets(): void
    {
        if (! $this->legacyTableExists('tbl_com_outlet')) {
            $this->warn('Tabel legacy tbl_com_outlet tidak ditemukan.');

            return;
        }

        $this->reloadBrandMap();
        $rows = $this->legacy()->table('tbl_com_outlet')->get();
        $stats = $this->emptyStats();

        $this->withTargetTransaction(function () use ($rows, &$stats): void {
            foreach ($rows as $row) {
                try {
                    $legacyId = (int) $this->val($row, 'id_outlet');
                    $code = $this->normalizeCode($this->val($row, 'kode_outlet', 'OUT-'.$legacyId));
                    $legacyBrandId = (int) $this->val($row, 'id_brand');
                    $brandId = $this->brandMap[$legacyBrandId] ?? null;

                    if (! $brandId) {
                        throw new \RuntimeException("Brand legacy id {$legacyBrandId} tidak ditemukan.");
                    }

                    $name = $this->title($this->val($row, 'nama_outlet', $code));

                    if ($this->isDryRun) {
                        $existing = $this->findTarget('outlets', [
                            'tenant_id' => $this->tenantId,
                            'code' => $code,
                        ]);
                        $this->outletMap[$legacyId] = $existing?->id ?? $this->fakeId($legacyId);
                        $stats[$existing ? 'skipped' : 'inserted']++;
                        $this->line(($existing ? '  [SKIP] ' : '  [UPSERT] ')."Outlet {$code} - {$name}");
                        continue;
                    }

                    $this->outletMap[$legacyId] = $this->upsertGetId('outlets', [
                        'tenant_id' => $this->tenantId,
                        'code' => $code,
                    ], [
                        'brand_id' => $brandId,
                        'legal_entity_id' => $this->defaultLegalEntityId,
                        'name' => $name,
                        'outlet_type' => 'OUTLET',
                        'timezone' => 'Asia/Jakarta',
                        'address' => $this->nullableString($this->val($row, 'alamat_outlet')),
                        'contact_phone' => $this->nullableString($this->val($row, 'kontak_outlet', $this->val($row, 'phone'))),
                        'status' => $this->statusFromLegacy($this->val($row, 'status_outlet', $this->val($row, 'status', 'AKTIF'))),
                    ]);
                    $stats['inserted']++;
                } catch (Throwable $e) {
                    $stats['errors']++;
                    $this->logRowError('outlets', $row, $e);
                }
            }
        });

        $this->summary('Outlets', $stats);
    }

    private function migrateDepartments(): void
    {
        if (! $this->legacyTableExists('tbl_departemen')) {
            $this->warn('Tabel legacy tbl_departemen tidak ditemukan.');

            return;
        }

        $rows = $this->legacy()->table('tbl_departemen')->get();
        $stats = $this->emptyStats();

        $this->withTargetTransaction(function () use ($rows, &$stats): void {
            foreach ($rows as $row) {
                try {
                    $legacyId = (int) $this->val($row, 'id_com_departemen', $this->val($row, 'id_departemen', 0));
                    $name = $this->title($this->val($row, 'nama_departemen', $this->val($row, 'name', 'Department '.$legacyId)));
                    $code = $this->normalizeCode($this->val($row, 'kode_departemen', $name));

                    if ($this->isDryRun) {
                        $existing = $this->findTarget('departments', [
                            'tenant_id' => $this->tenantId,
                            'code' => $code,
                        ]);
                        $this->deptMap[$legacyId] = $existing?->id ?? $this->fakeId($legacyId);
                        $stats[$existing ? 'skipped' : 'inserted']++;
                        $this->line(($existing ? '  [SKIP] ' : '  [UPSERT] ')."Department {$code} - {$name}");
                        continue;
                    }

                    $this->deptMap[$legacyId] = $this->upsertGetId('departments', [
                        'tenant_id' => $this->tenantId,
                        'code' => $code,
                    ], [
                        'name' => $name,
                        'is_operational' => true,
                        'status' => 'ACTIVE',
                    ]);
                    $stats['inserted']++;
                } catch (Throwable $e) {
                    $stats['errors']++;
                    $this->logRowError('departments', $row, $e);
                }
            }
        });

        $this->summary('Departments', $stats);
    }

    private function migrateJenises(): void
    {
        if (! $this->legacyTableExists('tbl_data_jenis_bahan')) {
            $this->warn('Tabel legacy tbl_data_jenis_bahan tidak ditemukan.');

            return;
        }

        $rows = $this->legacy()->table('tbl_data_jenis_bahan')->get();
        $stats = $this->emptyStats();

        $this->withTargetTransaction(function () use ($rows, &$stats): void {
            foreach ($rows as $index => $row) {
                try {
                    $legacyId = (int) $this->val($row, 'id_jenis_bahan');
                    $legacyCode = $this->normalizeCode($this->val($row, 'kode_jenis_bahan', 'JNS-'.$legacyId));
                    $code = $this->jenisCodeMap()[$legacyCode] ?? $legacyCode;
                    $name = $this->title($this->val($row, 'nama_jenis_bahan', str_replace('_', ' ', $code)));

                    if ($this->isDryRun) {
                        $existing = $this->findTarget('item_jenises', [
                            'tenant_id' => $this->tenantId,
                            'code' => $code,
                        ]);
                        $this->jenisMap[$legacyId] = $existing?->id ?? $this->fakeId($legacyId);
                        $stats[$existing ? 'skipped' : 'inserted']++;
                        $this->line(($existing ? '  [SKIP] ' : '  [UPSERT] ')."Jenis {$code} - {$name}");
                        continue;
                    }

                    $this->jenisMap[$legacyId] = $this->upsertGetId('item_jenises', [
                        'tenant_id' => $this->tenantId,
                        'code' => $code,
                    ], [
                        'name' => $name,
                        'color' => $this->jenisColor($code),
                        'description' => $this->nullableString($this->val($row, 'keterangan')),
                        'is_active' => true,
                        'sort_order' => $index,
                    ]);
                    $stats['inserted']++;
                } catch (Throwable $e) {
                    $stats['errors']++;
                    $this->logRowError('jenises', $row, $e);
                }
            }
        });

        $this->summary('Jenises', $stats);
    }

    private function migrateCategories(): void
    {
        if (! $this->legacyTableExists('tbl_data_kategori_bahan')) {
            $this->warn('Tabel legacy tbl_data_kategori_bahan tidak ditemukan.');

            return;
        }

        $rows = $this->legacy()->table('tbl_data_kategori_bahan')->get();
        $stats = $this->emptyStats();

        $this->withTargetTransaction(function () use ($rows, &$stats): void {
            foreach ($rows as $index => $row) {
                try {
                    $legacyId = (int) $this->val($row, 'id_kategori_bahan');
                    $code = $this->normalizeCode($this->val($row, 'kode_kategori', 'CAT-'.$legacyId));
                    $name = $this->title($this->val($row, 'nama_kategori', $code));

                    if ($this->isDryRun) {
                        $existing = $this->findTarget('item_categories', [
                            'tenant_id' => $this->tenantId,
                            'code' => $code,
                        ]);
                        $this->categoryMap[$legacyId] = $existing?->id ?? $this->fakeId($legacyId);
                        $stats[$existing ? 'skipped' : 'inserted']++;
                        $this->line(($existing ? '  [SKIP] ' : '  [UPSERT] ')."Kategori {$code} - {$name}");
                        continue;
                    }

                    $this->categoryMap[$legacyId] = $this->upsertGetId('item_categories', [
                        'tenant_id' => $this->tenantId,
                        'code' => $code,
                    ], [
                        'parent_id' => null,
                        'name' => $name,
                        'description' => $this->nullableString($this->val($row, 'keterangan')),
                        'status' => 'ACTIVE',
                        'is_active' => true,
                        'sort_order' => $index,
                    ]);
                    $stats['inserted']++;
                } catch (Throwable $e) {
                    $stats['errors']++;
                    $this->logRowError('categories', $row, $e);
                }
            }
        });

        $this->summary('Categories', $stats);
    }

    private function migrateItems(): void
    {
        if (! $this->legacyTableExists('tbl_bahan_baku')) {
            $this->warn('Tabel legacy tbl_bahan_baku tidak ditemukan.');

            return;
        }

        $this->prepareFreshItemsIfRequested();
        $this->reloadAllMaps();

        $rows = $this->legacy()->table('tbl_bahan_baku')->orderBy('id_bahan_baku')->get();
        $stats = $this->emptyStats();

        $this->withTargetTransaction(function () use ($rows, &$stats): void {
            $bar = $this->output->createProgressBar($rows->count());
            $bar->start();

            foreach ($rows as $row) {
                $bar->advance();

                try {
                    $legacyId = (int) $this->val($row, 'id_bahan_baku');
                    $sku = trim((string) $this->val($row, 'sku', ''));
                    $sku = $sku !== '' ? $sku : 'LEGACY-'.$legacyId;

                    $baseUnitId = $this->unitMap[(int) $this->val($row, 'id_satuan')] ?? null;
                    $inventoryUnitId = $this->unitMap[(int) $this->val($row, 'id_satuan_inventory')] ?? $baseUnitId;
                    $purchaseUnitId = $this->unitMap[(int) $this->val($row, 'id_satuan_pembelian')] ?? $inventoryUnitId;
                    $jenisId = $this->jenisMap[(int) $this->val($row, 'id_jenis_bahan')] ?? null;
                    $categoryId = $this->categoryMap[(int) $this->val($row, 'id_kategori_bahan')] ?? null;
                    $deptId = $this->deptMap[(int) $this->val($row, 'id_departemen')] ?? null;

                    if (! $baseUnitId || ! $inventoryUnitId) {
                        throw new \RuntimeException('Unit dasar/inventory tidak ditemukan.');
                    }

                    $name = $this->title($this->val($row, 'nama_bahan', $sku));

                    if ($this->isDryRun) {
                        $existing = $this->findTarget('items', [
                            'tenant_id' => $this->tenantId,
                            'canonical_sku' => $sku,
                        ]);
                        $this->itemMap[$legacyId] = $existing?->id ?? $this->fakeId($legacyId);
                        $stats[$existing ? 'skipped' : 'inserted']++;
                        continue;
                    }

                    $this->itemMap[$legacyId] = $this->upsertGetId('items', [
                        'tenant_id' => $this->tenantId,
                        'canonical_sku' => $sku,
                    ], [
                        'name' => $name,
                        'description' => $this->nullableString($this->val($row, 'keterangan')),
                        'item_type' => $this->itemTypeFromLegacy((int) $this->val($row, 'id_jenis_bahan')),
                        'item_jenis_id' => $jenisId,
                        'item_category_id' => $categoryId,
                        'inventory_unit_id' => $inventoryUnitId,
                        'purchase_unit_id' => $purchaseUnitId,
                        'base_unit_id' => $baseUnitId,
                        'inventory_ratio' => $this->decimal($this->val($row, 'rasio_inventory_ke_dasar', 1), 1),
                        'purchase_ratio' => $this->decimal($this->val($row, 'rasio_pembelian_ke_dasar', 1), 1),
                        'yield_pct' => $this->decimal($this->val($row, 'yield_persen', 100), 100),
                        'opname_frequency' => 'DAILY',
                        'primary_department_id' => $deptId,
                        'track_expiry' => false,
                        'last_purchase_price' => $this->decimal($this->val($row, 'harga_beli_terakhir', 0), 0),
                        'standard_cost' => $this->decimal($this->val($row, 'standard_cost', 0), 0),
                        'track_stock' => true,
                        'is_active' => $this->activeBool($this->val($row, 'is_active', $this->val($row, 'status_aktif', 1))),
                    ]);
                    $stats['inserted']++;
                } catch (Throwable $e) {
                    $stats['errors']++;
                    $this->logRowError('items', $row, $e);
                }
            }

            $bar->finish();
            $this->newLine();
        });

        $this->summary('Items', $stats);
    }

    private function migrateItemOutlets(): void
    {
        if (! $this->legacyTableExists('tbl_bahan_baku_outlet')) {
            $this->warn('Tabel legacy tbl_bahan_baku_outlet tidak ditemukan.');

            return;
        }

        $this->reloadAllMaps();
        $rows = $this->legacy()->table('tbl_bahan_baku_outlet')->get();
        $stats = $this->emptyStats();

        $this->withTargetTransaction(function () use ($rows, &$stats): void {
            foreach ($rows as $row) {
                try {
                    $itemId = $this->itemMap[(int) $this->val($row, 'id_bahan_baku')] ?? null;
                    $outletId = $this->outletMap[(int) $this->val($row, 'id_outlet')] ?? null;

                    if (! $itemId || ! $outletId) {
                        throw new \RuntimeException('Item atau outlet tidak ditemukan.');
                    }

                    if ($this->isDryRun) {
                        $existing = $this->findTarget('item_outlets', [
                            'tenant_id' => $this->tenantId,
                            'item_id' => $itemId,
                            'outlet_id' => $outletId,
                        ]);
                        $stats[$existing ? 'skipped' : 'inserted']++;
                        continue;
                    }

                    $this->upsertGetId('item_outlets', [
                        'tenant_id' => $this->tenantId,
                        'item_id' => $itemId,
                        'outlet_id' => $outletId,
                    ], [
                        'status' => $this->activeBool($this->val($row, 'status_aktif', 1)) ? 'ACTIVE' : 'INACTIVE',
                        'opname_frequency' => null,
                        'is_active' => $this->activeBool($this->val($row, 'status_aktif', 1)),
                    ]);
                    $stats['inserted']++;
                } catch (Throwable $e) {
                    $stats['errors']++;
                    $this->logRowError('item-outlets', $row, $e);
                }
            }
        });

        $this->summary('Item-Outlets', $stats);
    }

    private function migrateOpenStocks(): void
    {
        if (! $this->legacyTableExists('tbl_open_stock')) {
            $this->warn('Tabel legacy tbl_open_stock tidak ditemukan.');

            return;
        }

        $this->reloadAllMaps();
        $query = $this->legacy()->table('tbl_open_stock');

        if ($this->legacyColumnExists('tbl_open_stock', 'status_posting')) {
            $query->where('status_posting', 'POSTED');
        }

        $rows = $query->get();
        $stats = $this->emptyStats();

        $this->withTargetTransaction(function () use ($rows, &$stats): void {
            foreach ($rows as $row) {
                try {
                    $itemId = $this->itemMap[(int) $this->val($row, 'id_bahan_baku')] ?? null;
                    $outletId = $this->outletMap[(int) $this->val($row, 'id_outlet')] ?? null;
                    $unitId = $this->unitMap[(int) $this->val($row, 'id_satuan')] ?? null;

                    if (! $itemId || ! $outletId || ! $unitId) {
                        throw new \RuntimeException('Item, outlet, atau unit tidak ditemukan.');
                    }

                    $businessDate = $this->dateString($this->val($row, 'tanggal_stok_awal', $this->val($row, 'business_date', now())));
                    $target = $this->stockTarget($this->val($row, 'target_stok', 'OUTLET_DAILY'));

                    $keys = [
                        'tenant_id' => $this->tenantId,
                        'outlet_id' => $outletId,
                        'item_id' => $itemId,
                        'stock_target' => $target,
                        'business_date' => $businessDate,
                    ];

                    if ($this->isDryRun) {
                        $existing = $this->findTarget('open_stocks', $keys);
                        $stats[$existing ? 'skipped' : 'inserted']++;
                        continue;
                    }

                    $existing = $this->findTarget('open_stocks', $keys);
                    if ($existing) {
                        $stats['skipped']++;
                        continue;
                    }

                    DB::table('open_stocks')->insert(array_merge($keys, [
                        'department_id' => null,
                        'unit_id' => $unitId,
                        'qty_whole' => $this->decimal($this->val($row, 'qty_utuh', 0), 0),
                        'qty_loose' => $this->decimal($this->val($row, 'qty_ecer', 0), 0),
                        'qty_in_base_unit' => $this->decimal($this->val($row, 'qty_posted', $this->val($row, 'qty_awal', 0)), 0),
                        'cost_per_unit' => $this->decimal($this->val($row, 'harga_satuan', 0), 0),
                        'status' => 'POSTED',
                        'created_by' => 1,
                        'posted_by' => 1,
                        'posted_at' => $this->dateTimeString($this->val($row, 'tanggal_posting', $this->val($row, 'updated_at', now()))),
                        'notes' => trim('Migrated from FBI '.$this->nullableString($this->val($row, 'catatan'))),
                        'created_at' => $this->dateTimeString($this->val($row, 'tanggal_dibuat', $this->val($row, 'created_at', now()))),
                        'updated_at' => now(),
                    ]));
                    $stats['inserted']++;
                } catch (Throwable $e) {
                    $stats['errors']++;
                    $this->logRowError('open-stocks', $row, $e);
                }
            }
        });

        $this->summary('Open Stocks', $stats);
    }

    private function migrateUsers(): void
    {
        if (! $this->legacyTableExists('tbl_pengguna')) {
            $this->warn('Tabel legacy tbl_pengguna tidak ditemukan.');

            return;
        }

        $this->reloadAllMaps();

        $fbiUsers = $this->legacy()
            ->table('tbl_pengguna')
            ->where('status_pengguna', 'AKTIF')
            ->get();

        $roleMap = [
            'ADMINISTRATOR'      => 'SUPER_ADMIN',
            'FINANCE ACCOUNTING' => 'GENERAL_FINANCE',
            'MANAGER AREA'       => 'MANAGER_AREA',
            'PIC OUTLET'         => 'PIC_OUTLET',
            'STAFF DEPARTEMEN'   => 'STAFF_BAR',
            'USER'               => 'STAFF_BAR',
        ];

        $defaultPassword = 'Sifobi@2026!';
        $inserted = 0;
        $skipped = 0;
        $errors = 0;
        $usersWithTempPassword = [];

        foreach ($fbiUsers as $fbi) {
            if (strtoupper($fbi->username) === 'ADMIN') {
                $skipped++;
                if ($this->isDryRun) {
                    $this->line("  [SKIP] {$fbi->username} — akun admin sudah ada");
                }
                continue;
            }

            $email = $fbi->email;
            $isDummyEmail = (
                str_ends_with(strtolower((string) $email), '@example.com') ||
                str_ends_with(strtolower((string) $email), '@gmail.com') ||
                empty($email)
            );
            if ($isDummyEmail) {
                $cleanUsername = strtolower(str_replace([' ', '.'], '-', (string) $fbi->username));
                $email = $cleanUsername.'@mykopiogroup.com';
            }

            $emailFinal = $email;
            $suffix = 2;
            while (DB::table('users')->where('email', $emailFinal)->exists()) {
                $emailFinal = str_replace('@', $suffix.'@', $email);
                $suffix++;
            }

            $existingByName = DB::table('users')
                ->where('tenant_id', $this->tenantId)
                ->where('name', ucwords(strtolower((string) $fbi->nama_lengkap)))
                ->first();

            if ($existingByName) {
                $skipped++;
                if ($this->isDryRun) {
                    $this->line("  [SKIP] {$fbi->username} sudah ada");
                }
                continue;
            }

            $outletId = null;
            if (! empty($fbi->id_outlet) && (int) $fbi->id_outlet > 0) {
                $outletId = $this->outletMap[(int) $fbi->id_outlet] ?? null;
            }

            $roleName = $roleMap[$fbi->id_hak_akses] ?? 'STAFF_BAR';

            $hasBcrypt = ! empty($fbi->password) && str_starts_with((string) $fbi->password, '$2y$');
            $password = $hasBcrypt ? $fbi->password : bcrypt($defaultPassword);
            $needsReset = ! $hasBcrypt;

            if ($this->isDryRun) {
                $this->line(sprintf(
                    '  [INSERT] %s → %s | role: %s | outlet: %s | pass: %s',
                    $fbi->username,
                    $emailFinal,
                    $roleName,
                    $outletId ? "id={$outletId}" : 'none',
                    $hasBcrypt ? 'bcrypt lama (langsung login)' : 'sementara: '.$defaultPassword
                ));
                $inserted++;
                continue;
            }

            try {
                DB::transaction(function () use (
                    $fbi, $emailFinal, $password, $outletId, $roleName, $needsReset, &$usersWithTempPassword
                ): void {
                    $userId = DB::table('users')->insertGetId([
                        'tenant_id'  => $this->tenantId,
                        'outlet_id'  => $outletId,
                        'name'       => ucwords(strtolower((string) $fbi->nama_lengkap)),
                        'email'      => $emailFinal,
                        'password'   => $password,
                        'phone'      => null,
                        'status'     => 'active',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    $user = \App\Models\User::find($userId);
                    if ($user) {
                        $user->assignRole($roleName);
                    }

                    if ($needsReset) {
                        $usersWithTempPassword[] = [
                            'username' => $fbi->username,
                            'email'    => $emailFinal,
                            'role'     => $roleName,
                        ];
                    }
                });
                $inserted++;
            } catch (Throwable $e) {
                $this->warn("  [ERROR] {$fbi->username}: ".$e->getMessage());
                Log::warning("MigrateFBI User {$fbi->username}: ".$e->getMessage());
                $errors++;
            }
        }

        $this->info("  ✅ Users: {$inserted} ditambah, {$skipped} skip, {$errors} error");

        if (! $this->isDryRun && count($usersWithTempPassword) > 0) {
            $this->newLine();
            $this->warn('  ⚠️  '.count($usersWithTempPassword)." user mendapat password sementara: {$defaultPassword}");
            $this->warn('  Sampaikan ke user untuk segera ganti password setelah login pertama.');
            $this->newLine();
            $this->table(
                ['Username FBI', 'Email SIFOBI', 'Role'],
                array_map(fn ($u) => [$u['username'], $u['email'], $u['role']], $usersWithTempPassword)
            );

            $logPath = storage_path('logs/migrated-users-'.date('Y-m-d-His').'.txt');
            $lines = ['USER MIGRASI FBI → SIFOBI — '.now()."\n"];
            $lines[] = "PASSWORD SEMENTARA: {$defaultPassword}\n";
            $lines[] = "Minta user ganti password setelah login pertama.\n\n";
            foreach ($usersWithTempPassword as $u) {
                $lines[] = "{$u['username']} | {$u['email']} | {$u['role']}\n";
            }
            file_put_contents($logPath, implode('', $lines));
            $this->info("  📄 Log tersimpan di: {$logPath}");
        }
    }

    private function withTargetTransaction(callable $callback): void
    {
        if ($this->isDryRun) {
            $callback();

            return;
        }

        DB::transaction(fn () => $callback());
    }

    /**
     * @param  array<string, mixed>  $keys
     * @param  array<string, mixed>  $values
     */
    private function upsertGetId(string $table, array $keys, array $values): int
    {
        $existing = $this->findTarget($table, $keys);

        if ($existing) {
            DB::table($table)
                ->where('id', $existing->id)
                ->update(array_merge($values, ['updated_at' => now()]));

            return (int) $existing->id;
        }

        return (int) DB::table($table)->insertGetId(array_merge($keys, $values, [
            'created_at' => now(),
            'updated_at' => now(),
        ]));
    }

    /**
     * @param  array<string, mixed>  $keys
     */
    private function findTarget(string $table, array $keys): ?object
    {
        $query = DB::table($table);

        foreach ($keys as $column => $value) {
            $query->where($column, $value);
        }

        return $query->first();
    }

    private function prepareFreshItemsIfRequested(): void
    {
        if (! $this->option('fresh-items')) {
            return;
        }

        $count = DB::table('items')->where('tenant_id', $this->tenantId)->count();

        if ($this->isDryRun) {
            $this->warn("[DRY-RUN] --fresh-items akan menghapus {$count} item existing jika dijalankan tanpa dry-run.");

            return;
        }

        if ($count <= 0) {
            return;
        }

        if (! $this->confirm("Hapus {$count} item existing di SIFOBI tenant {$this->tenantId}?")) {
            $this->warn('--fresh-items dibatalkan oleh user.');

            return;
        }

        DB::transaction(function (): void {
            DB::table('item_outlets')->where('tenant_id', $this->tenantId)->delete();
            DB::table('open_stocks')->where('tenant_id', $this->tenantId)->delete();

            if ($this->targetTableExists('item_aliases')) {
                DB::table('item_aliases')->where('tenant_id', $this->tenantId)->delete();
            }

            if ($this->targetTableExists('item_brand_aliases')) {
                DB::table('item_brand_aliases')->where('tenant_id', $this->tenantId)->delete();
            }

            if ($this->targetTableExists('item_departments')) {
                DB::table('item_departments')->whereIn('item_id', function ($query): void {
                    $query->select('id')->from('items')->where('tenant_id', $this->tenantId);
                })->delete();
            }

            if ($this->targetTableExists('item_department_maps')) {
                DB::table('item_department_maps')->where('tenant_id', $this->tenantId)->delete();
            }

            DB::table('items')->where('tenant_id', $this->tenantId)->delete();
        });

        $this->warn('Item existing tenant 1 dihapus karena --fresh-items.');
    }

    private function reloadAllMaps(): void
    {
        $this->reloadUnitMap();
        $this->reloadBrandMap();
        $this->reloadOutletMap();
        $this->reloadDepartmentMap();
        $this->reloadJenisMap();
        $this->reloadCategoryMap();
        $this->reloadItemMap();
    }

    private function reloadUnitMap(): void
    {
        if (! $this->legacyTableExists('tbl_data_satuan')) {
            return;
        }

        $legacy = $this->legacy()->table('tbl_data_satuan')->get();
        $target = DB::table('units')->where('tenant_id', $this->tenantId)->get(['id', 'code']);

        foreach ($legacy as $row) {
            $legacyCode = $this->normalizeCode($this->val($row, 'kode_satuan', 'NA'), 'NA');
            $code = $this->unitCodeMap()[$legacyCode] ?? $legacyCode;
            $found = $target->firstWhere('code', $code);

            if ($found) {
                $this->unitMap[(int) $this->val($row, 'id_satuan')] = (int) $found->id;
            }
        }
    }

    private function reloadBrandMap(): void
    {
        if ($this->legacyTableExists('tbl_com_group')) {
            $legacyGroups = $this->legacy()->table('tbl_com_group')->get();
            $targetGroups = DB::table('groups')->where('tenant_id', $this->tenantId)->get(['id', 'code']);

            foreach ($legacyGroups as $row) {
                $code = $this->normalizeCode($this->val($row, 'kode_group', 'FBI'));
                $found = $targetGroups->firstWhere('code', $code);

                if ($found) {
                    $legacyId = (int) $this->val($row, 'id_group', $this->val($row, 'id_com_group', 0));
                    $this->groupMap[$legacyId] = (int) $found->id;
                }
            }
        }

        if (! $this->legacyTableExists('tbl_com_brand')) {
            return;
        }

        $legacy = $this->legacy()->table('tbl_com_brand')->get();
        $target = DB::table('brands')->where('tenant_id', $this->tenantId)->get(['id', 'code']);

        foreach ($legacy as $row) {
            $code = $this->normalizeCode($this->val($row, 'kode_brand', ''));
            $found = $target->firstWhere('code', $code);

            if ($found) {
                $this->brandMap[(int) $this->val($row, 'id_brand')] = (int) $found->id;
            }
        }
    }

    private function reloadOutletMap(): void
    {
        if (! $this->legacyTableExists('tbl_com_outlet')) {
            return;
        }

        $legacy = $this->legacy()->table('tbl_com_outlet')->get();
        $target = DB::table('outlets')->where('tenant_id', $this->tenantId)->get(['id', 'code']);

        foreach ($legacy as $row) {
            $code = $this->normalizeCode($this->val($row, 'kode_outlet', ''));
            $found = $target->firstWhere('code', $code);

            if ($found) {
                $this->outletMap[(int) $this->val($row, 'id_outlet')] = (int) $found->id;
            }
        }
    }

    private function reloadDepartmentMap(): void
    {
        if (! $this->legacyTableExists('tbl_departemen')) {
            return;
        }

        $legacy = $this->legacy()->table('tbl_departemen')->get();
        $target = DB::table('departments')->where('tenant_id', $this->tenantId)->get(['id', 'code']);

        foreach ($legacy as $row) {
            $legacyId = (int) $this->val($row, 'id_com_departemen', $this->val($row, 'id_departemen', 0));
            $code = $this->normalizeCode($this->val($row, 'kode_departemen', $this->val($row, 'nama_departemen', '')));
            $found = $target->firstWhere('code', $code);

            if ($found) {
                $this->deptMap[$legacyId] = (int) $found->id;
            }
        }
    }

    private function reloadJenisMap(): void
    {
        if (! $this->legacyTableExists('tbl_data_jenis_bahan')) {
            return;
        }

        $legacy = $this->legacy()->table('tbl_data_jenis_bahan')->get();
        $target = DB::table('item_jenises')->where('tenant_id', $this->tenantId)->get(['id', 'code']);

        foreach ($legacy as $row) {
            $legacyCode = $this->normalizeCode($this->val($row, 'kode_jenis_bahan', ''));
            $code = $this->jenisCodeMap()[$legacyCode] ?? $legacyCode;
            $found = $target->firstWhere('code', $code);

            if ($found) {
                $this->jenisMap[(int) $this->val($row, 'id_jenis_bahan')] = (int) $found->id;
            }
        }
    }

    private function reloadCategoryMap(): void
    {
        if (! $this->legacyTableExists('tbl_data_kategori_bahan')) {
            return;
        }

        $legacy = $this->legacy()->table('tbl_data_kategori_bahan')->get();
        $target = DB::table('item_categories')->where('tenant_id', $this->tenantId)->get(['id', 'code']);

        foreach ($legacy as $row) {
            $code = $this->normalizeCode($this->val($row, 'kode_kategori', ''));
            $found = $target->firstWhere('code', $code);

            if ($found) {
                $this->categoryMap[(int) $this->val($row, 'id_kategori_bahan')] = (int) $found->id;
            }
        }
    }

    private function reloadItemMap(): void
    {
        if (! $this->legacyTableExists('tbl_bahan_baku')) {
            return;
        }

        $legacy = $this->legacy()->table('tbl_bahan_baku')->get(['id_bahan_baku', 'sku']);
        $target = DB::table('items')->where('tenant_id', $this->tenantId)->get(['id', 'canonical_sku']);

        foreach ($legacy as $row) {
            $legacyId = (int) $this->val($row, 'id_bahan_baku');
            $sku = trim((string) $this->val($row, 'sku', ''));
            $sku = $sku !== '' ? $sku : 'LEGACY-'.$legacyId;
            $found = $target->firstWhere('canonical_sku', $sku);

            if ($found) {
                $this->itemMap[$legacyId] = (int) $found->id;
            }
        }
    }

    private function legacy(): ConnectionInterface
    {
        return DB::connection('fbi_legacy');
    }

    private function legacyTableExists(string $table): bool
    {
        try {
            return $this->legacy()
                ->table('information_schema.tables')
                ->whereRaw('table_schema = database()')
                ->where('table_name', $table)
                ->exists();
        } catch (Throwable) {
            return false;
        }
    }

    private function legacyColumnExists(string $table, string $column): bool
    {
        try {
            return $this->legacy()
                ->table('information_schema.columns')
                ->whereRaw('table_schema = database()')
                ->where('table_name', $table)
                ->where('column_name', $column)
                ->exists();
        } catch (Throwable) {
            return false;
        }
    }

    private function targetTableExists(string $table): bool
    {
        return DB::getSchemaBuilder()->hasTable($table);
    }

    private function defaultGroupId(): ?int
    {
        return DB::table('groups')->where('tenant_id', $this->tenantId)->value('id');
    }

    /**
     * @param  string|array<int, string>  $keys
     */
    private function val(object $row, string|array $keys, mixed $default = null): mixed
    {
        foreach ((array) $keys as $key) {
            if (property_exists($row, $key)) {
                return $row->{$key};
            }
        }

        return $default;
    }

    private function normalizeCode(mixed $value, string $fallback = 'NA'): string
    {
        $code = strtoupper(trim((string) ($value ?: $fallback)));
        $code = str_replace([' ', '/', '\\', '.', '-'], '_', $code);
        $code = preg_replace('/[^A-Z0-9_]/', '', $code) ?: $fallback;

        return substr($code, 0, 32);
    }

    private function title(mixed $value): string
    {
        $text = trim((string) $value);

        if ($text === '') {
            return '-';
        }

        return mb_convert_case(mb_strtolower($text), MB_CASE_TITLE, 'UTF-8');
    }

    private function nullableString(mixed $value): ?string
    {
        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }

    private function decimal(mixed $value, float $default): float
    {
        if ($value === null || $value === '') {
            return $default;
        }

        return (float) str_replace(',', '.', (string) $value);
    }

    private function activeBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        $normalized = strtoupper(trim((string) $value));

        return ! in_array($normalized, ['0', 'FALSE', 'NO', 'N', 'INACTIVE', 'NONAKTIF', 'TIDAK'], true);
    }

    private function statusFromLegacy(mixed $value): string
    {
        return $this->activeBool($value) ? 'ACTIVE' : 'INACTIVE';
    }

    private function itemTypeFromLegacy(int $legacyJenisId): string
    {
        $code = null;

        if ($this->legacyTableExists('tbl_data_jenis_bahan')) {
            $row = $this->legacy()->table('tbl_data_jenis_bahan')
                ->where('id_jenis_bahan', $legacyJenisId)
                ->first();
            $code = $row ? $this->normalizeCode($this->val($row, 'kode_jenis_bahan', '')) : null;
        }

        return match ($code) {
            'WIP' => 'WIP_L1',
            'MENU' => 'MENU_ITEM',
            'PACKAGING' => 'PACKAGING',
            default => 'BAHAN_BAKU',
        };
    }

    private function stockTarget(mixed $value): string
    {
        $target = strtoupper(trim((string) $value));

        return in_array($target, ['GUDANG_UTAMA', 'WAREHOUSE', 'OUTLET_WAREHOUSE'], true)
            ? 'OUTLET_WAREHOUSE'
            : 'OUTLET_DAILY';
    }

    private function dateString(mixed $value): string
    {
        try {
            return Carbon::parse($value)->toDateString();
        } catch (Throwable) {
            return now()->toDateString();
        }
    }

    private function dateTimeString(mixed $value): string
    {
        try {
            return Carbon::parse($value)->toDateTimeString();
        } catch (Throwable) {
            return now()->toDateTimeString();
        }
    }

    private function fakeId(int $legacyId): int
    {
        return 900000000 + $legacyId;
    }

    /**
     * @return array<string, string>
     */
    private function unitCodeMap(): array
    {
        return [
            'N_A' => 'NA',
            'N/A' => 'NA',
        ];
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    private function unitNameMap(): array
    {
        return [
            'MG' => ['Miligram', 'mg'],
            'GR' => ['Gram', 'gr'],
            'KG' => ['Kilogram', 'kg'],
            'ML' => ['Mililiter', 'ml'],
            'L' => ['Liter', 'l'],
            'KL' => ['Kiloliter', 'kl'],
            'PCS' => ['Pieces', 'pcs'],
            'BTL' => ['Botol', 'btl'],
            'GLN' => ['Galon', 'gln'],
            'PACK' => ['Pack', 'pack'],
            'CAN' => ['Kaleng', 'can'],
            'ROLL' => ['Roll', 'roll'],
            'PORSI' => ['Porsi', 'porsi'],
            'LSN' => ['Lusin', 'lsn'],
            'BUAH' => ['Buah', 'buah'],
            'KD' => ['Kodi', 'kodi'],
            'RIM' => ['Rim', 'rim'],
            'LBR' => ['Lembar', 'lbr'],
            'CTN' => ['Carton', 'ctn'],
            'TBG' => ['Tabung', 'tbg'],
            'JER' => ['Jerigen', 'jer'],
            'SAK' => ['Sak / Karung', 'sak'],
            'PAIL' => ['Ember', 'pail'],
            'JAR' => ['Jar', 'jar'],
            'ZAK' => ['Zak', 'zak'],
            'TIN' => ['Tin', 'tin'],
            'SCH' => ['Sachet', 'sch'],
            'NA' => ['N/A', 'n/a'],
        ];
    }

    private function decimalPlacesForUnit(string $code): int
    {
        return in_array($code, ['PCS', 'BTL', 'PACK', 'CAN', 'ROLL', 'PORSI', 'LSN', 'BUAH', 'KD', 'RIM', 'LBR', 'CTN', 'TBG', 'JER', 'SAK', 'PAIL', 'JAR', 'ZAK', 'TIN', 'SCH'], true)
            ? 0
            : 3;
    }

    /**
     * @return array<string, string>
     */
    private function jenisCodeMap(): array
    {
        return [
            'RM' => 'RAW_MATERIAL',
            'RAW' => 'RAW_MATERIAL',
            'RAW_MATERIAL' => 'RAW_MATERIAL',
            'DRYGOOD' => 'DRYGOOD',
            'DG' => 'DRYGOOD',
            'WIP' => 'WIP',
            'MENU' => 'MENU',
            'NRM' => 'NON_RAW_MATERIAL',
            'NON_RAW_MATERIAL' => 'NON_RAW_MATERIAL',
        ];
    }

    private function jenisColor(string $code): string
    {
        return match ($code) {
            'RAW_MATERIAL' => 'green',
            'DRYGOOD' => 'amber',
            'WIP' => 'purple',
            'MENU' => 'rose',
            'NON_RAW_MATERIAL' => 'blue',
            default => 'gray',
        };
    }

    /**
     * @return array{inserted: int, skipped: int, errors: int}
     */
    private function emptyStats(): array
    {
        return [
            'inserted' => 0,
            'skipped' => 0,
            'errors' => 0,
        ];
    }

    /**
     * @param  array{inserted: int, skipped: int, errors: int}  $stats
     */
    private function summary(string $label, array $stats): void
    {
        $this->info("  {$label}: {$stats['inserted']} upsert/insert, {$stats['skipped']} skip, {$stats['errors']} error");
    }

    private function logRowError(string $stage, object $row, Throwable $e): void
    {
        $payload = json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $this->warn("  [ERROR] {$stage}: ".$e->getMessage());
        Log::warning("MigrateFbiLegacyData row error [{$stage}]: ".$e->getMessage(), [
            'row' => $payload,
        ]);
    }

    private function printTargetCounts(): void
    {
        $tables = [
            'units',
            'brands',
            'outlets',
            'departments',
            'item_jenises',
            'item_categories',
            'items',
            'item_outlets',
            'open_stocks',
            'users',
        ];

        foreach ($tables as $table) {
            if (! $this->targetTableExists($table)) {
                continue;
            }

            $this->line($table.': '.DB::table($table)->count());
        }
    }
}
