<?php

namespace App\Imports;

use App\Modules\Inventory\Models\Item;
use App\Modules\Inventory\Models\ItemCategory;
use App\Modules\Inventory\Models\Unit;
use App\Support\Decimal;
use Closure;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\SkipsErrors;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\SkipsOnError;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Validators\Failure;
use Throwable;

class ItemsImport implements SkipsOnError, SkipsOnFailure, ToCollection, WithBatchInserts, WithChunkReading, WithHeadingRow, WithValidation
{
    use Importable;
    use SkipsErrors;
    use SkipsFailures;

    private int $inserted = 0;

    private int $updated = 0;

    /**
     * @var list<array{row:int,message:string}>
     */
    private array $rowErrors = [];

    public function __construct(private readonly int $tenantId)
    {
    }

    public function collection(Collection $rows): void
    {
        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2;

            try {
                $this->upsertRow($row, $rowNumber);
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
            '*.canonical_sku' => ['required', 'string', 'max:50'],
            '*.name' => ['required', 'string', 'max:255'],
            '*.item_type' => ['required', 'in:BAHAN_BAKU,WIP_L1,WIP_L2,WIP_L3,PACKAGING,MENU_ITEM'],
            '*.base_unit' => ['required', 'string'],
            '*.inventory_ratio' => ['required', Decimal::validationRule(8), $this->decimalMinRule('0.0001')],
            '*.purchase_ratio' => ['required', Decimal::validationRule(8), $this->decimalMinRule('0.0001')],
            '*.yield_pct' => ['nullable', Decimal::validationRule(2), $this->decimalBetweenRule('0', '100')],
            '*.last_purchase_price' => ['nullable', Decimal::validationRule(4), $this->decimalMinRule('0')],
            '*.is_active' => ['nullable', 'in:0,1'],
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

    public function batchSize(): int
    {
        return 250;
    }

    public function chunkSize(): int
    {
        return 250;
    }

    /**
     * @return array{success:bool,inserted:int,updated:int,failed:int,errors:list<array{row:int,message:string}>}
     */
    public function summary(): array
    {
        return [
            'success' => $this->failed() === 0,
            'inserted' => $this->inserted,
            'updated' => $this->updated,
            'failed' => $this->failed(),
            'errors' => $this->rowErrors,
        ];
    }

    private function failed(): int
    {
        return count($this->rowErrors);
    }

    private function upsertRow(Collection $row, int $rowNumber): void
    {
        $sku = trim((string) $row->get('canonical_sku'));
        $baseUnit = $this->findUnit((string) $row->get('base_unit'));
        $inventoryUnit = $this->findUnit((string) ($row->get('inventory_unit') ?: $row->get('base_unit')));
        $purchaseUnit = $this->findUnit((string) ($row->get('purchase_unit') ?: $row->get('base_unit')));

        if (! $baseUnit) {
            throw new \RuntimeException("Unit '{$row->get('base_unit')}' tidak ditemukan.");
        }

        if (! $inventoryUnit) {
            throw new \RuntimeException("Inventory unit '{$row->get('inventory_unit')}' tidak ditemukan.");
        }

        if (! $purchaseUnit) {
            throw new \RuntimeException("Purchase unit '{$row->get('purchase_unit')}' tidak ditemukan.");
        }

        $category = $this->category((string) $row->get('item_category'));
        $existing = Item::query()
            ->where('tenant_id', $this->tenantId)
            ->where('canonical_sku', $sku)
            ->first();

        $attributes = [
            'item_category_id' => $category?->id,
            'base_unit_id' => $baseUnit->id,
            'inventory_unit_id' => $inventoryUnit->id,
            'purchase_unit_id' => $purchaseUnit->id,
            'name' => trim((string) $row->get('name')),
            'description' => $row->get('description'),
            'item_type' => trim((string) $row->get('item_type')),
            'inventory_ratio' => Decimal::toFixed($row->get('inventory_ratio')),
            'purchase_ratio' => Decimal::toFixed($row->get('purchase_ratio')),
            'yield_pct' => $row->get('yield_pct') === null || $row->get('yield_pct') === '' ? null : Decimal::toFixed($row->get('yield_pct'), 2),
            'last_purchase_price' => $row->get('last_purchase_price') === null || $row->get('last_purchase_price') === '' ? null : Decimal::toFixed($row->get('last_purchase_price'), 4),
            'standard_cost' => $row->get('last_purchase_price') === null || $row->get('last_purchase_price') === '' ? 0 : Decimal::toFixed($row->get('last_purchase_price'), 4),
            'track_stock' => true,
            'is_active' => $row->get('is_active') === null || $row->get('is_active') === '' ? true : (bool) ((int) $row->get('is_active')),
        ];

        if ($existing) {
            $existing->update($attributes);
            $this->updated++;

            return;
        }

        Item::query()->create(array_merge($attributes, [
            'tenant_id' => $this->tenantId,
            'canonical_sku' => $sku,
        ]));

        $this->inserted++;
    }

    private function findUnit(string $value): ?Unit
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        return Unit::query()
            ->where('tenant_id', $this->tenantId)
            ->where(function ($query) use ($value): void {
                $query->where('code', $value)
                    ->orWhere('code', strtoupper($value))
                    ->orWhere('abbreviation', $value)
                    ->orWhere('abbreviation', strtolower($value));
            })
            ->first();
    }

    private function category(string $name): ?ItemCategory
    {
        $name = trim($name);

        if ($name === '') {
            return null;
        }

        $code = Str::of($name)->upper()->replaceMatches('/[^A-Z0-9]+/', '_')->trim('_')->limit(32, '')->toString();

        return ItemCategory::query()->updateOrCreate(
            ['tenant_id' => $this->tenantId, 'code' => $code],
            ['name' => $name, 'status' => 'ACTIVE']
        );
    }

    private function decimalMinRule(string $minimum): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($minimum): void {
            if ($value === null || $value === '') {
                return;
            }

            try {
                $normalized = Decimal::normalize($value, 8);
            } catch (\InvalidArgumentException) {
                return;
            }

            if ((float) $normalized < (float) $minimum) {
                $fail("The {$attribute} field must be at least {$minimum}.");
            }
        };
    }

    private function decimalBetweenRule(string $minimum, string $maximum): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($minimum, $maximum): void {
            if ($value === null || $value === '') {
                return;
            }

            try {
                $normalized = Decimal::normalize($value, 8);
            } catch (\InvalidArgumentException) {
                return;
            }

            if ((float) $normalized < (float) $minimum || (float) $normalized > (float) $maximum) {
                $fail("The {$attribute} field must be between {$minimum} and {$maximum}.");
            }
        };
    }
}
