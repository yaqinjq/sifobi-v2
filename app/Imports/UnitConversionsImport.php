<?php

namespace App\Imports;

use App\Modules\Inventory\Models\Item;
use App\Modules\Inventory\Models\Unit;
use App\Modules\Inventory\Models\UnitConversion;
use App\Support\Decimal;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class UnitConversionsImport implements ToCollection, WithHeadingRow
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

            try {
                $item = Item::query()->where('tenant_id', $this->tenantId)->where('canonical_sku', trim((string) $row->get('item_sku')))->first();
                $fromUnit = $this->unit((string) $row->get('from_unit'));
                $toUnit = $this->unit((string) $row->get('to_unit'));

                if (! $item || ! $fromUnit || ! $toUnit) {
                    $this->errors[] = ['row' => $rowNumber, 'message' => 'Item atau unit konversi tidak ditemukan.'];
                    continue;
                }

                $factor = Decimal::toFixed($row->get('factor'), 8);
                $existing = UnitConversion::query()
                    ->where('tenant_id', $this->tenantId)
                    ->where('item_id', $item->id)
                    ->where('from_unit_id', $fromUnit->id)
                    ->where('to_unit_id', $toUnit->id)
                    ->first();

                UnitConversion::query()->updateOrCreate(
                    [
                        'tenant_id' => $this->tenantId,
                        'item_id' => $item->id,
                        'from_unit_id' => $fromUnit->id,
                        'to_unit_id' => $toUnit->id,
                    ],
                    [
                        'multiply_rate' => $factor,
                        'factor' => $factor,
                    ]
                );

                $existing ? $this->updated++ : $this->inserted++;
            } catch (\InvalidArgumentException $exception) {
                $this->errors[] = ['row' => $rowNumber, 'message' => $exception->getMessage()];
            }
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

    private function unit(string $value): ?Unit
    {
        $value = trim($value);

        return Unit::query()
            ->where('tenant_id', $this->tenantId)
            ->where(fn ($query) => $query
                ->where('code', $value)
                ->orWhere('code', strtoupper($value))
                ->orWhere('abbreviation', $value)
                ->orWhere('abbreviation', strtolower($value))
            )
            ->first();
    }
}
