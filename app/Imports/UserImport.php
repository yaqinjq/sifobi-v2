<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Koordinator 2-sheet. WAJIB WithMultipleSheets -- kalau cuma
 * ToCollection biasa, Maatwebsite memanggil collection() 1x PER SHEET
 * pakai instance yang SAMA, menimpa data sheet pertama saat sheet kedua
 * diproses (dicek di vendor/maatwebsite/excel/src/Reader.php). Key array
 * di sheets() HARUS persis nama sheet Excel (case-sensitive).
 */
class UserImport implements WithMultipleSheets
{
    private NewUserRowsImport $newUsers;

    private DeactivateTrialUserRowsImport $deactivations;

    public function __construct()
    {
        $this->newUsers = new NewUserRowsImport();
        $this->deactivations = new DeactivateTrialUserRowsImport();
    }

    /**
     * @return array<string, object>
     */
    public function sheets(): array
    {
        return [
            'Import User' => $this->newUsers,
            'Nonaktifkan Akun Trial' => $this->deactivations,
        ];
    }

    public function newUsersImport(): NewUserRowsImport
    {
        return $this->newUsers;
    }

    public function deactivationsImport(): DeactivateTrialUserRowsImport
    {
        return $this->deactivations;
    }

    /**
     * @return array{new_users: array, deactivations: array}
     */
    public function preview(int $tenantId): array
    {
        return [
            'new_users' => $this->newUsers->preview($tenantId),
            'deactivations' => $this->deactivations->preview($tenantId),
        ];
    }
}
