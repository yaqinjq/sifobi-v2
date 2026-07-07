<?php

namespace App\Http\Requests\Operations;

use App\Modules\Operations\Models\OpenStock;
use App\Support\Decimal;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBulkOpenStockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('input_open_stock') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $tenantId = (int) $this->user()->tenant_id;

        return [
            'outlet_id'    => ['required', 'integer', Rule::exists('outlets', 'id')->where('tenant_id', $tenantId)],
            'stock_target' => ['required', 'string', Rule::in(array_keys(OpenStock::targetOptions()))],
            'business_date'=> ['required', 'date'],
            'batch_notes'  => ['nullable', 'string', 'max:500'],

            'items'              => ['required', 'array', 'min:1', $this->noDuplicateItems()],
            'items.*.item_id'    => ['required', 'integer', Rule::exists('items', 'id')->where('tenant_id', $tenantId)],
            'items.*.department_id' => ['required', 'integer', Rule::exists('departments', 'id')->where('tenant_id', $tenantId)],
            'items.*.targets'    => ['nullable', 'array', 'min:1'],
            'items.*.targets.*'  => ['string', Rule::in(array_keys(OpenStock::targetOptions()))],
            'items.*.qty_whole'  => ['required', Decimal::validationRule()],
            'items.*.qty_loose'  => ['required', Decimal::validationRule()],
            'items.*.qty_purchase' => ['nullable', Decimal::validationRule()],
            'items.*.cost_per_unit' => ['nullable', Decimal::validationRule(4)],
            'items.*.notes'      => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'items.required' => 'Minimal satu item harus ditambahkan ke daftar.',
            'items.min'      => 'Minimal satu item harus ditambahkan ke daftar.',
            'items.*.item_id.required' => 'Item ID wajib ada.',
            'items.*.item_id.exists'   => 'Item tidak ditemukan di database.',
            'items.*.department_id.required' => 'Departemen wajib dipilih di setiap baris.',
            'items.*.qty_whole.required' => 'Qty utuh wajib diisi.',
            'items.*.qty_loose.required' => 'Qty ecer wajib diisi.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $stockTarget = $this->normalizeStockTarget($this->input('stock_target', $this->input('target', OpenStock::TARGET_OUTLET_DAILY)));

        $items = collect($this->input('items', []))->map(function (array $row) use ($stockTarget): array {
            $targets = $row['targets']
                ?? $row['stock_targets']
                ?? $row['stock_target']
                ?? $row['target']
                ?? [$stockTarget];

            $targets = is_array($targets) ? $targets : [$targets];
            $targets = array_values(array_unique(array_filter(array_map(
                fn (mixed $target): mixed => $this->normalizeStockTarget($target),
                $targets
            ))));

            $row['targets'] = $targets === [] ? [$stockTarget] : $targets;
            $row['qty_whole'] = $this->decimalOrZero($row['qty_whole'] ?? '0');
            $row['qty_loose'] = $this->decimalOrZero($row['qty_loose'] ?? '0');
            $row['qty_purchase'] = $this->decimalOrZero($row['qty_purchase'] ?? '0');
            $row['notes'] = $row['catatan_baris'] ?? $row['notes'] ?? $this->input('catatan');

            return $row;
        })->all();

        $this->merge([
            'stock_target' => $stockTarget,
            'business_date' => $this->input('business_date', $this->input('date')),
            'batch_notes' => $this->input('batch_notes', $this->input('catatan')),
            'items' => $items,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        $user = $this->user();

        $fallbackTarget = $this->string('stock_target')->toString();
        $items = collect($this->input('items', []))
            ->flatMap(function (array $row) use ($fallbackTarget): array {
                $targets = $row['targets'] ?? [$fallbackTarget];

                return collect($targets)->map(function (string $target) use ($row): array {
                    $isWarehouse = $target === OpenStock::TARGET_OUTLET_WAREHOUSE;
                    $qtyWhole = $isWarehouse
                        ? $this->decimalOrZero($row['qty_purchase'] ?? $row['qty_whole'] ?? '0')
                        : $this->decimalOrZero($row['qty_whole'] ?? '0');

                    return [
                        'item_id'      => (int) $row['item_id'],
                        'department_id'=> (int) $row['department_id'],
                        'stock_target' => $target,
                        'qty_whole'    => Decimal::toFixed($qtyWhole),
                        'qty_loose'    => $isWarehouse ? Decimal::toFixed('0') : Decimal::toFixed($this->decimalOrZero($row['qty_loose'] ?? '0')),
                        'cost_per_unit'=> isset($row['cost_per_unit']) && $row['cost_per_unit'] !== ''
                            ? Decimal::toFixed($row['cost_per_unit'], 4)
                            : null,
                        'notes'        => $row['notes'] ?? null,
                    ];
                })->all();
            })
            ->values()
            ->all();

        return [
            'tenant_id'    => $user->tenant_id,
            'outlet_id'    => $this->integer('outlet_id'),
            'stock_target' => $this->string('stock_target')->toString(),
            'business_date'=> $this->date('business_date')->toDateString(),
            'created_by'   => $user->id,
            'batch_notes'  => $this->input('batch_notes'),
            'items'        => $items,
        ];
    }

    private function noDuplicateItems(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail): void {
            if (! is_array($value)) {
                return;
            }

            $fallbackTarget = (string) $this->input('stock_target', OpenStock::TARGET_OUTLET_DAILY);
            $keys = [];

            foreach ($value as $row) {
                if (! is_array($row) || ! isset($row['item_id'])) {
                    continue;
                }

                $targets = $row['targets'] ?? [$fallbackTarget];
                $targets = is_array($targets) ? $targets : [$targets];

                foreach ($targets as $target) {
                    $keys[] = $row['item_id'].':'.$target;
                }
            }

            if (count($keys) !== count(array_unique($keys))) {
                $fail('Item dan target stok yang sama tidak boleh duplikat dalam satu batch.');
            }
        };
    }

    private function normalizeStockTarget(mixed $target): mixed
    {
        return match ($target) {
            'STOK_HARIAN_OUTLET' => OpenStock::TARGET_OUTLET_DAILY,
            'GUDANG_UTAMA' => OpenStock::TARGET_OUTLET_WAREHOUSE,
            default => $target,
        };
    }

    private function decimalOrZero(mixed $value): mixed
    {
        return $value === null || $value === '' ? '0' : $value;
    }
}
