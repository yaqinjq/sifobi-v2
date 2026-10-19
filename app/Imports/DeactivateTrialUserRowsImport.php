<?php

namespace App\Imports;

use App\Http\Controllers\Concerns\HasBulkAction;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Sheet "Nonaktifkan Akun Trial" -- daftar id+email akun EXISTING yang
 * perlu dinonaktifkan (BUKAN dihapus -- sebagian besar py riwayat
 * transaksi). Cuma menonaktifkan, reuse HasBulkAction::runBulkAction()
 * yang sama dgn UserController::bulkToggleStatus() (trait ini murni
 * loop+try/catch, tidak bergantung Controller).
 */
class DeactivateTrialUserRowsImport implements ToCollection, WithHeadingRow
{
    use HasBulkAction;
    use Importable;

    private Collection $rows;

    public function collection(Collection $rows): void
    {
        $this->rows = $rows;
    }

    /**
     * @return array{rows: list<array<string, mixed>>, total: int, will_deactivate: int, not_found: int, already_inactive: int}
     */
    public function preview(int $tenantId): array
    {
        $rows = $this->rows ?? collect();
        $out = [];

        foreach ($rows as $row) {
            $out[] = $this->evaluateRow($row, $tenantId);
        }

        return [
            'rows' => $out,
            'total' => count($out),
            'will_deactivate' => count(array_filter($out, fn ($r) => $r['status'] === 'will_deactivate')),
            'not_found' => count(array_filter($out, fn ($r) => $r['status'] === 'not_found')),
            'already_inactive' => count(array_filter($out, fn ($r) => $r['status'] === 'already_inactive')),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function evaluateRow(Collection $row, int $tenantId): array
    {
        $id = (int) ($row['id'] ?? 0);
        $email = Str::lower(trim((string) ($row['email'] ?? '')));

        $base = [
            'id' => $id,
            'email' => $email,
            'role' => $row['role'] ?? null,
            'outlet' => $row['outlet'] ?? null,
            'login_terakhir' => $row['login_terakhir'] ?? null,
        ];

        $user = $id > 0
            ? User::withoutGlobalScopes()->where('tenant_id', $tenantId)->find($id)
            : null;

        if (! $user || Str::lower($user->email) !== $email) {
            return $base + ['status' => 'not_found', 'message' => 'ID dan email tidak cocok dengan data tenant ini.'];
        }

        if (strtoupper((string) $user->status) !== 'ACTIVE') {
            return $base + ['status' => 'already_inactive', 'message' => 'Sudah non-aktif sebelumnya.'];
        }

        return $base + ['status' => 'will_deactivate', 'message' => null];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array{processed: int, failed: int, errors: list<string>}
     */
    public function applyChanges(array $rows, int $tenantId, User $actingUser): array
    {
        $ids = collect($rows)
            ->where('status', 'will_deactivate')
            ->pluck('id')
            ->filter()
            ->all();

        $users = User::query()
            ->whereIn('id', $ids)
            ->where('tenant_id', $tenantId)
            ->where('id', '!=', $actingUser->id)
            ->where('status', 'ACTIVE')
            ->get();

        return $this->runBulkAction(
            $users,
            fn (User $user) => $user->update(['status' => 'INACTIVE']),
            fn (User $user) => $user->name
        );
    }
}
