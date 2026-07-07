<?php

namespace App\Imports;

use App\Modules\Core\Models\Department;
use App\Modules\Inventory\Models\Item;
use App\Modules\Operations\Models\OpenStock;
use App\Services\OpenStockService;
use App\Support\Decimal;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\SkipsErrors;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\SkipsOnError;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Validators\Failure;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Throwable;

class OpenStockImport implements SkipsOnError, SkipsOnFailure, ToCollection, WithBatchInserts, WithHeadingRow, WithValidation
{
    use Importable;
    use SkipsErrors;
    use SkipsFailures;

    private int $inserted = 0;

    /**
     * @var list<array{row:int,message:string}>
     */
    private array $rowErrors = [];

    public function __construct(
        private readonly int $tenantId,
        private readonly int $outletId,
        private readonly int $userId,
        private readonly OpenStockService $openStockService
    ) {
    }

    public function collection(Collection $rows): void
    {
        $groups = [];

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2;

            try {
                $payload = $this->payloadFromRow($row, $rowNumber);
                $groupKey = $payload['business_date'].'|'.$payload['stock_target'];
                $groups[$groupKey]['business_date'] = $payload['business_date'];
                $groups[$groupKey]['stock_target'] = $payload['stock_target'];
                $groups[$groupKey]['items'][] = $payload['item'];
            } catch (Throwable $throwable) {
                $this->rowErrors[] = [
                    'row' => $rowNumber,
                    'message' => $throwable->getMessage(),
                ];
            }
        }

        foreach ($groups as $group) {
            $created = $this->openStockService->createBatchDraft([
                'tenant_id' => $this->tenantId,
                'outlet_id' => $this->outletId,
                'stock_target' => $group['stock_target'],
                'business_date' => $group['business_date'],
                'created_by' => $this->userId,
                'items' => $group['items'],
            ], $this->userId);

            $this->inserted += $created->count();
        }
    }

    public function rules(): array
    {
        return [
            '*.tanggal_stok_awal' => ['required'],
            '*.item_sku' => ['required'],
            '*.departemen_code' => ['required'],
            '*.target' => ['required'],
            '*.qty_whole' => ['nullable'],
            '*.qty_loose' => ['nullable'],
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

    public function summary(): array
    {
        return [
            'inserted' => $this->inserted,
            'failed' => count($this->rowErrors),
            'errors' => collect($this->rowErrors)
                ->map(fn (array $error): string => "Baris {$error['row']}: {$error['message']}")
                ->values()
                ->all(),
        ];
    }

    private function payloadFromRow(Collection $row, int $rowNumber): array
    {
        $item = Item::query()
            ->where('tenant_id', $this->tenantId)
            ->where('canonical_sku', trim((string) $row->get('item_sku')))
            ->where('is_active', true)
            ->first();

        if (! $item) {
            throw new \RuntimeException("SKU '{$row->get('item_sku')}' tidak ditemukan.");
        }

        $department = Department::query()
            ->where('tenant_id', $this->tenantId)
            ->where('code', strtoupper(trim((string) $row->get('departemen_code'))))
            ->where('status', 'ACTIVE')
            ->first();

        if (! $department) {
            throw new \RuntimeException("Departemen '{$row->get('departemen_code')}' tidak ditemukan.");
        }

        $target = $this->target((string) $row->get('target'));
        $qtyWhole = Decimal::toFixed($row->get('qty_whole') ?? '0');
        $qtyLoose = $target === OpenStock::TARGET_OUTLET_WAREHOUSE
            ? '0.000000'
            : Decimal::toFixed($row->get('qty_loose') ?? '0');

        return [
            'business_date' => $this->date($row->get('tanggal_stok_awal')),
            'stock_target' => $target,
            'item' => [
                'department_id' => $department->id,
                'item_id' => $item->id,
                'qty_whole' => $qtyWhole,
                'qty_loose' => $qtyLoose,
                'notes' => $row->get('catatan'),
            ],
        ];
    }

    private function target(string $target): string
    {
        return match (strtoupper(trim($target))) {
            'STOK_HARIAN_OUTLET', OpenStock::TARGET_OUTLET_DAILY => OpenStock::TARGET_OUTLET_DAILY,
            'GUDANG_UTAMA', OpenStock::TARGET_OUTLET_WAREHOUSE => OpenStock::TARGET_OUTLET_WAREHOUSE,
            default => throw new \RuntimeException("Target '{$target}' tidak valid."),
        };
    }

    private function date(mixed $value): string
    {
        if (is_numeric($value)) {
            return Carbon::instance(ExcelDate::excelToDateTimeObject((float) $value))->toDateString();
        }

        $value = trim((string) $value);

        if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $value) === 1) {
            return Carbon::createFromFormat('d/m/Y', $value)->toDateString();
        }

        return Carbon::parse($value)->toDateString();
    }
}
