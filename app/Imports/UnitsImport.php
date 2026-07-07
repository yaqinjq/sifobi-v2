<?php

namespace App\Imports;

use App\Modules\Inventory\Models\Unit;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class UnitsImport implements ToCollection, WithHeadingRow
{
    use Importable;

    private int $inserted = 0;

    private int $updated = 0;

    /**
     * @var list<array{row:int,message:string}>
     */
    private array $errors = [];

    public function __construct(private readonly int $tenantId)
    {
    }

    public function collection(Collection $rows): void
    {
        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2;
            $code = trim((string) $row->get('code'));
            $name = trim((string) $row->get('name'));

            if ($code === '' || $name === '') {
                $this->errors[] = ['row' => $rowNumber, 'message' => 'Code dan name wajib diisi.'];
                continue;
            }

            $unit = Unit::query()->where('tenant_id', $this->tenantId)->where('code', $code)->first();

            Unit::query()->updateOrCreate(
                ['tenant_id' => $this->tenantId, 'code' => $code],
                [
                    'name' => $name,
                    'abbreviation' => $row->get('abbreviation') ?: strtolower($code),
                    'status' => 'ACTIVE',
                ]
            );

            $unit ? $this->updated++ : $this->inserted++;
        }
    }

    public function summary(): array
    {
        return [
            'success' => $this->errors === [],
            'inserted' => $this->inserted,
            'updated' => $this->updated,
            'failed' => count($this->errors),
            'errors' => $this->errors,
        ];
    }
}

