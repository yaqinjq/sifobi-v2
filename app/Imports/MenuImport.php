<?php

namespace App\Imports;

use App\Modules\Production\Models\Menu;
use App\Modules\Production\Models\MenuCategory;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithColumnLimit;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithReadFilter;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;

/**
 * Import master menu dari file export "Master Menu" POS perusahaan --
 * judul laporan di baris 1-4, header kolom di baris 6 (Menu Code, Menu Name,
 * Category Detail, Status, ...). Upsert per (tenant, brand, kode menu):
 * tabel menus tidak punya unique index di code, jadi dicocokkan manual.
 * selling_price sengaja tidak disentuh (file tidak punya harga) -- menu baru
 * tetap NULL sehingga tidak muncul di POS internal sampai harganya diisi.
 */
class MenuImport implements ToCollection, WithColumnLimit, WithHeadingRow, WithReadFilter
{
    /**
     * File export POS ini membawa ~1.000 kolom kosong "hantu" (sampai AMJ)
     * -- tanpa batas ini PhpSpreadsheet memuat ~500 ribu sel dan habis
     * memory (terbukti crash di 128 MB). Data aslinya cuma kolom A..V.
     */
    private const LAST_COLUMN = 'V';

    private int $inserted = 0;

    private int $updated = 0;

    private int $skipped = 0;

    private int $categoriesCreated = 0;

    /** @var list<string> */
    private array $errors = [];

    /** @var array<string, MenuCategory> */
    private array $categoryCache = [];

    public function __construct(
        private readonly int $tenantId,
        private readonly int $brandId,
    ) {}

    public function headingRow(): int
    {
        return 6;
    }

    public function endColumn(): string
    {
        return self::LAST_COLUMN;
    }

    public function readFilter(): IReadFilter
    {
        return new class(Coordinate::columnIndexFromString(self::LAST_COLUMN)) implements IReadFilter
        {
            public function __construct(private readonly int $lastColumnIndex) {}

            public function readCell($columnAddress, $row, $worksheetName = '')
            {
                return Coordinate::columnIndexFromString((string) $columnAddress) <= $this->lastColumnIndex;
            }
        };
    }

    public function collection(Collection $rows): void
    {
        $existing = Menu::withoutGlobalScopes()
            ->where('tenant_id', $this->tenantId)
            ->where('brand_id', $this->brandId)
            ->whereNotNull('code')
            ->get()
            ->keyBy(fn (Menu $menu) => Str::upper($menu->code));

        $seenInFile = [];

        DB::transaction(function () use ($rows, $existing, &$seenInFile): void {
            foreach ($rows as $index => $row) {
                $excelRow = $index + $this->headingRow() + 1;
                $code = trim((string) ($row['menu_code'] ?? ''));
                $name = trim((string) ($row['menu_name'] ?? ''));

                if ($code === '' || $name === '') {
                    $this->skipped++;

                    continue;
                }

                if (mb_strlen($code) > 32 || mb_strlen($name) > 150) {
                    $this->errors[] = "Baris {$excelRow}: kode (maks 32) atau nama menu (maks 150) terlalu panjang -- {$code}.";

                    continue;
                }

                // Kode yang sama dipakai 2 menu BERBEDA di 1 file (terjadi di
                // export asli: APFSID047) -- jangan diam-diam timpa menu
                // pertama, laporkan supaya kodenya dibetulkan di sumbernya.
                $codeKey = Str::upper($code);

                if (isset($seenInFile[$codeKey])) {
                    $this->errors[] = "Baris {$excelRow}: kode {$code} sudah dipakai menu \"{$seenInFile[$codeKey]}\" di baris sebelumnya -- \"{$name}\" tidak diimport.";

                    continue;
                }

                $seenInFile[$codeKey] = $name;

                $attributes = [
                    'name' => $name,
                    'menu_category_id' => $this->category((string) ($row['category_detail'] ?? ''))?->id,
                    'is_active' => Str::lower(trim((string) ($row['status'] ?? 'active'))) === 'active',
                ];

                $menu = $existing->get(Str::upper($code));

                if ($menu) {
                    $menu->fill($attributes)->save();
                    $this->updated++;

                    continue;
                }

                $menu = Menu::withoutGlobalScopes()->create($attributes + [
                    'tenant_id' => $this->tenantId,
                    'brand_id' => $this->brandId,
                    'code' => $code,
                ]);
                $existing->put(Str::upper($code), $menu);
                $this->inserted++;
            }
        });
    }

    /**
     * @return array{inserted: int, updated: int, skipped: int, categories_created: int, errors: list<string>}
     */
    public function summary(): array
    {
        return [
            'inserted' => $this->inserted,
            'updated' => $this->updated,
            'skipped' => $this->skipped,
            'categories_created' => $this->categoriesCreated,
            'errors' => $this->errors,
        ];
    }

    private function category(string $name): ?MenuCategory
    {
        $name = trim($name);

        if ($name === '') {
            return null;
        }

        $code = Str::of($name)->upper()->replaceMatches('/[^A-Z0-9]+/', '_')->trim('_')->limit(50, '')->toString();

        if (isset($this->categoryCache[$code])) {
            return $this->categoryCache[$code];
        }

        $category = MenuCategory::withoutGlobalScopes()->firstOrCreate(
            ['tenant_id' => $this->tenantId, 'code' => $code],
            ['name' => $name, 'is_active' => true],
        );

        if ($category->wasRecentlyCreated) {
            $this->categoriesCreated++;
        }

        return $this->categoryCache[$code] = $category;
    }
}
