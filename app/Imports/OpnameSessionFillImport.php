<?php

namespace App\Imports;

use App\Modules\Operations\Models\OpnameSession;
use App\Services\OpnameService;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Throwable;

/**
 * Importer "Isi Massal lewat Excel" untuk 1 sesi Opname -- BEDA dengan
 * OpnameMonthlyImport (yang bisa membuat+submit+approve banyak sesi
 * sekaligus): importer ini HANYA mengisi OpnameItem yang sudah ada di
 * 1 sesi DRAFT lewat OpnameService::updateItem(), setara otorisasi
 * dengan edit manual per item -- tidak pernah submit/approve apa pun.
 */
class OpnameSessionFillImport implements ToCollection, WithHeadingRow
{
    use Importable;

    private Collection $rows;

    public function collection(Collection $rows): void
    {
        $this->rows = $rows;
    }

    /**
     * @return array{applied: int, skipped_blank: int, not_found: list<string>, errors: list<string>}
     */
    public function applyTo(OpnameSession $session, OpnameService $service, ?int $userDepartmentId): array
    {
        if ($session->status !== OpnameSession::STATUS_DRAFT) {
            throw ValidationException::withMessages([
                'status' => 'Hanya sesi draft yang bisa diisi lewat template.',
            ]);
        }

        $sessionItems = $session->items()->with(['item', 'department'])->get();

        // Key "sku|departemen" untuk disambiguasi item shared antar-departemen
        // (1 SKU bisa punya >1 baris OpnameItem kalau sesi lintas-departemen).
        $bySkuDept = [];
        $bySkuOnly = [];
        foreach ($sessionItems as $opnameItem) {
            $sku = strtolower(trim((string) $opnameItem->item?->canonical_sku));
            if ($sku === '') {
                continue;
            }
            $dept = strtolower(trim((string) ($opnameItem->department?->name ?? $opnameItem->item?->primaryDepartment?->name ?? '')));
            $bySkuDept["{$sku}|{$dept}"] = $opnameItem;
            $bySkuOnly[$sku][] = $opnameItem;
        }

        $applied = 0;
        $skippedBlank = 0;
        $notFound = [];
        $errors = [];

        foreach ($this->rows ?? collect() as $row) {
            $sku = strtolower(trim((string) ($row['sku'] ?? '')));
            if ($sku === '') {
                continue;
            }

            $whole = trim((string) ($row['fisik_utuh'] ?? ''));
            $loose = trim((string) ($row['fisik_ecer'] ?? ''));

            if ($whole === '' && $loose === '') {
                $skippedBlank++;
                continue;
            }

            $dept = strtolower(trim((string) ($row['departemen'] ?? '')));
            $opnameItem = $bySkuDept["{$sku}|{$dept}"]
                ?? (count($bySkuOnly[$sku] ?? []) === 1 ? $bySkuOnly[$sku][0] : null);

            if (! $opnameItem) {
                $label = (string) ($row['item'] ?? $sku);
                $notFound[] = isset($bySkuOnly[$sku])
                    ? "{$label}: SKU ada di sesi ini tapi departemen pada file tidak cocok (ada ".count($bySkuOnly[$sku]).' baris)'
                    : "{$label}: SKU tidak ditemukan dalam sesi ini";
                continue;
            }

            if ($userDepartmentId
                && $opnameItem->department_id
                && (int) $opnameItem->department_id !== (int) $userDepartmentId) {
                $errors[] = "{$opnameItem->item?->name}: dilewati -- bukan item departemen Anda";
                continue;
            }

            try {
                $service->updateItem($opnameItem, $whole !== '' ? $whole : '0', $loose !== '' ? $loose : '0');
                $applied++;
            } catch (ValidationException $e) {
                throw $e;
            } catch (Throwable $e) {
                $errors[] = "{$opnameItem->item?->name}: {$e->getMessage()}";
            }
        }

        return [
            'applied' => $applied,
            'skipped_blank' => $skippedBlank,
            'not_found' => $notFound,
            'errors' => $errors,
        ];
    }
}
