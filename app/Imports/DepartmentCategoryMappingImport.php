<?php

namespace App\Imports;

use App\Modules\Core\Models\Department;
use App\Modules\Inventory\Models\ItemCategory;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\SkipsErrors;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\SkipsOnError;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Validators\Failure;
use Throwable;

/**
 * Import mapping Departemen -> Kategori -> Sub Kategori (dengan urutan) dari
 * Excel, mengikuti format "Mapping Daily Opname.xlsx" yang disiapkan
 * pimpinan. Tujuannya supaya filter Kategori di halaman Opname cuma
 * menampilkan kategori yang relevan untuk departemen sesi tsb (bukan semua
 * kategori master), dan item di dalam kategori tampil sesuai urutan form,
 * bukan abjad -- lihat Department::itemCategories() dan
 * ItemCategory::parent()/children().
 *
 * Upsert idempoten: baris yang sama (departemen+kategori+sub kategori) yang
 * diimpor ulang cuma memperbarui urutan, tidak membuat duplikat.
 */
class DepartmentCategoryMappingImport implements SkipsEmptyRows, SkipsOnError, SkipsOnFailure, ToCollection, WithChunkReading, WithHeadingRow, WithValidation
{
    use Importable;
    use SkipsErrors;
    use SkipsFailures;

    private int $processed = 0;

    /**
     * @var list<array{row:int,message:string}>
     */
    private array $rowErrors = [];

    public function __construct(
        private readonly int $tenantId,
    ) {
    }

    public function collection(Collection $rows): void
    {
        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2;

            try {
                $this->importRow($row);
                $this->processed++;
            } catch (Throwable $throwable) {
                $this->rowErrors[] = [
                    'row' => $rowNumber,
                    'message' => $throwable->getMessage(),
                ];
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            '*.departemen' => ['required', 'string'],
            '*.kategori' => ['required', 'string'],
            '*.sub_kategori' => ['nullable', 'string'],
            '*.urutan' => ['nullable', 'integer'],
        ];
    }

    public function onFailure(Failure ...$failures): void
    {
        foreach ($failures as $failure) {
            $this->rowErrors[] = [
                'row' => $failure->row(),
                'message' => implode('; ', $failure->errors()),
            ];
        }
    }

    public function onError(Throwable $e): void
    {
        $this->rowErrors[] = [
            'row' => 0,
            'message' => $e->getMessage(),
        ];
    }

    public function chunkSize(): int
    {
        return 200;
    }

    /**
     * @return array{success:bool,processed:int,failed:int,errors:list<array{row:int,message:string}>}
     */
    public function summary(): array
    {
        return [
            'success' => count($this->rowErrors) === 0,
            'processed' => $this->processed,
            'failed' => count($this->rowErrors),
            'errors' => $this->rowErrors,
        ];
    }

    private function importRow(Collection $row): void
    {
        $departmentLabel = trim((string) $row->get('departemen'));
        $department = Department::query()
            ->where('tenant_id', $this->tenantId)
            ->where(fn ($q) => $q->where('name', $departmentLabel)->orWhere('code', $departmentLabel))
            ->first();

        if (! $department) {
            throw new \RuntimeException("Departemen '{$departmentLabel}' tidak ditemukan.");
        }

        $categoryName = trim((string) $row->get('kategori'));

        $category = ItemCategory::query()
            ->where('tenant_id', $this->tenantId)
            ->whereNull('parent_id')
            ->where('name', $categoryName)
            ->first();

        if (! $category) {
            $category = ItemCategory::query()->create([
                'tenant_id' => $this->tenantId,
                'parent_id' => null,
                'code' => $this->uniqueCode($categoryName),
                'name' => $categoryName,
                'status' => 'ACTIVE',
                'is_active' => true,
                'sort_order' => 0,
            ]);
        }

        $department->itemCategories()->syncWithoutDetaching([$category->id]);

        $subCategoryName = trim((string) $row->get('sub_kategori', ''));

        if ($subCategoryName === '') {
            return;
        }

        $sortOrder = (int) ($row->get('urutan') ?: 0);

        $sub = ItemCategory::query()
            ->where('tenant_id', $this->tenantId)
            ->where('parent_id', $category->id)
            ->where('name', $subCategoryName)
            ->first();

        if ($sub) {
            $sub->update(['sort_order' => $sortOrder]);

            return;
        }

        ItemCategory::query()->create([
            'tenant_id' => $this->tenantId,
            'parent_id' => $category->id,
            'code' => $this->uniqueCode($categoryName.'-'.$subCategoryName),
            'name' => $subCategoryName,
            'status' => 'ACTIVE',
            'is_active' => true,
            'sort_order' => $sortOrder,
        ]);
    }

    private function uniqueCode(string $label): string
    {
        $base = Str::slug($label, '_');
        $code = mb_substr($base, 0, 50);
        $suffix = 1;

        while (ItemCategory::query()->where('tenant_id', $this->tenantId)->where('code', $code)->exists()) {
            $code = mb_substr($base, 0, 46).'_'.$suffix;
            $suffix++;
        }

        return $code;
    }
}
