<?php

namespace App\Imports;

use App\Modules\Core\Models\Department;
use App\Modules\Core\Models\Outlet;
use App\Modules\Inventory\Models\Item;
use App\Modules\Inventory\Models\Unit;
use App\Modules\Operations\Models\SpoilWaste;
use App\Services\SpoilWasteService;
use App\Support\Decimal;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Importable;
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
 * Import histori Spoil & Waste dari Excel — dibuat supaya tim tidak perlu
 * input satu-satu data lama yang sudah dicatat manual di spreadsheet.
 *
 * Tiap baris dijalankan lewat SpoilWasteService::record() APA ADANYA (bukan
 * jalur pintas baru) — supaya perilakunya identik dengan input manual lewat
 * form: qty tetap dipotong dari stock_balance SAAT INI (bukan saldo di
 * tanggal historisnya, sama seperti keterbatasan yang sudah didiskusikan
 * untuk Penerimaan Barang historis), dan tetap ditolak kalau stok saat ini
 * tidak cukup. Karena ini data historis yang sudah dikonfirmasi tim (bukan
 * kejadian baru yang perlu ditinjau), tiap baris yang berhasil otomatis
 * di-approve juga lewat approve() — supaya tidak ada kerja dobel klik
 * approve satu-satu untuk ratusan baris.
 */
class SpoilWasteImport implements SkipsOnError, SkipsOnFailure, ToCollection, WithChunkReading, WithHeadingRow, WithValidation
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
        private readonly int $userId,
        private readonly SpoilWasteService $service,
    ) {
    }

    public function collection(Collection $rows): void
    {
        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2;

            try {
                $this->importRow($row);
                $this->inserted++;
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
            '*.outlet' => ['required', 'string'],
            '*.sku' => ['required', 'string'],
            '*.unit' => ['required', 'string'],
            '*.qty' => ['required', Decimal::validationRule(6), $this->decimalMinRule('0.000001')],
            '*.tanggal_kejadian' => ['required', 'date'],
            '*.kategori_alasan' => ['required', 'string'],
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
        return 100;
    }

    /**
     * @return array{success:bool,inserted:int,failed:int,errors:list<array{row:int,message:string}>}
     */
    public function summary(): array
    {
        return [
            'success' => count($this->rowErrors) === 0,
            'inserted' => $this->inserted,
            'failed' => count($this->rowErrors),
            'errors' => $this->rowErrors,
        ];
    }

    private function importRow(Collection $row): void
    {
        $outlet = Outlet::query()
            ->where('tenant_id', $this->tenantId)
            ->where(fn ($q) => $q->where('name', trim((string) $row->get('outlet')))->orWhere('code', trim((string) $row->get('outlet'))))
            ->first();

        if (! $outlet) {
            throw new \RuntimeException("Outlet '{$row->get('outlet')}' tidak ditemukan.");
        }

        $department = null;
        $departmentName = trim((string) $row->get('departemen', ''));
        if ($departmentName !== '') {
            $department = Department::query()
                ->where('tenant_id', $this->tenantId)
                ->where(fn ($q) => $q->where('name', $departmentName)->orWhere('code', $departmentName))
                ->first();

            if (! $department) {
                throw new \RuntimeException("Departemen '{$departmentName}' tidak ditemukan.");
            }
        }

        $sku = trim((string) $row->get('sku'));
        $item = Item::query()->where('tenant_id', $this->tenantId)->where('canonical_sku', $sku)->first();

        if (! $item) {
            throw new \RuntimeException("Item dengan SKU '{$sku}' tidak ditemukan.");
        }

        $unit = $this->findUnit((string) $row->get('unit'));

        if (! $unit) {
            throw new \RuntimeException("Satuan '{$row->get('unit')}' tidak ditemukan.");
        }

        $reasonCategory = $this->reasonCategory((string) $row->get('kategori_alasan'));

        if (! $reasonCategory) {
            throw new \RuntimeException("Kategori alasan '{$row->get('kategori_alasan')}' tidak dikenali. Gunakan salah satu: ".implode(', ', array_values($this->reasonLabels())));
        }

        $spoil = $this->service->record([
            'tenant_id' => $this->tenantId,
            'outlet_id' => $outlet->id,
            'department_id' => $department?->id,
            'item_id' => $item->id,
            'unit_id' => $unit->id,
            'qty' => Decimal::toFixed($row->get('qty'), 6),
            'recorded_date' => (string) $row->get('tanggal_kejadian'),
            'recorded_at' => (string) $row->get('tanggal_kejadian'),
            'reason_category' => $reasonCategory,
            'reason_detail' => trim((string) $row->get('keterangan', '')) ?: null,
        ], $this->userId);

        $this->service->approve($spoil, $this->userId, 'Auto-approved: entri historis dari import Excel.');
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

    private function reasonCategory(string $label): ?string
    {
        $label = trim($label);
        $normalized = strtoupper($label);

        // Terima juga kode mentah (EXPIRED, RUSAK, dst.) selain label Indonesia.
        if (in_array($normalized, [
            SpoilWaste::REASON_EXPIRED, SpoilWaste::REASON_RUSAK, SpoilWaste::REASON_KESALAHAN_PRODUKSI,
            SpoilWaste::REASON_TUMPAH, SpoilWaste::REASON_QUALITY_REJECT, SpoilWaste::REASON_LAINNYA,
        ], true)) {
            return $normalized;
        }

        $map = array_flip(array_map('strtolower', $this->reasonLabels()));

        return $map[strtolower($label)] ?? null;
    }

    /**
     * @return array<string, string>
     */
    private function reasonLabels(): array
    {
        return [
            SpoilWaste::REASON_EXPIRED => 'Kadaluarsa',
            SpoilWaste::REASON_RUSAK => 'Rusak/Cacat',
            SpoilWaste::REASON_KESALAHAN_PRODUKSI => 'Kesalahan Produksi',
            SpoilWaste::REASON_TUMPAH => 'Tumpah',
            SpoilWaste::REASON_QUALITY_REJECT => 'Reject Kualitas',
            SpoilWaste::REASON_LAINNYA => 'Lainnya',
        ];
    }

    private function decimalMinRule(string $minimum): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) use ($minimum): void {
            try {
                if (bccomp(Decimal::toFixed($value, 6), Decimal::toFixed($minimum, 6), 6) < 0) {
                    $fail("Qty minimal {$minimum}.");
                }
            } catch (Throwable) {
                $fail('Qty tidak valid.');
            }
        };
    }
}
