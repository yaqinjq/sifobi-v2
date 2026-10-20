<?php

namespace App\Imports;

use App\Http\Controllers\Settings\UserController;
use App\Models\User;
use App\Support\Concerns\AssignsUserRoles;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Throwable;

/**
 * Sheet "Import User" -- akun STAFF baru. Header Excel di-slug Maatwebsite
 * (config('excel.imports.heading_row.formatter') = 'slug'), jadi
 * "Password (Nokom)" jadi key 'password_nokom', BUKAN 'password'.
 *
 * Password disimpan APA ADANYA dari Excel (plaintext pendek ala sistem
 * "Nokom" internal perusahaan) -- model User meng-cast 'password' =>
 * 'hashed', jadi Eloquent OTOMATIS hash saat save(). JANGAN PERNAH
 * panggil Hash::make() di sini -- itu akan hash dua kali (bug yang sama
 * persis ada di command artisan ad-hoc yang ditolak tim).
 *
 * PENTING: field hasil evaluasi baris dinamai 'row_status'
 * (creatable/duplicate_update/duplicate_no_change/skip_duplicate/error)
 * -- SENGAJA BUKAN 'status', supaya tidak bentrok dgn kolom Excel
 * "Status" (ACTIVE/INACTIVE akun itu sendiri). Dulu pernah collide pakai
 * nama yg sama -- `$base + ['status' => ...]` diam-diam TIDAK menimpa
 * nilai yang sudah ada (operator `+` array PHP keep nilai kiri), bikin
 * semua baris salah dikira berstatus akun, bukan status hasil evaluasi.
 *
 * Email yang SUDAH TERDAFTAR di tenant yang SAMA ditawarkan untuk
 * DIPERBARUI (duplicate_update), bukan cuma dilewati -- supaya re-import
 * bisa dipakai memperbaiki data yang salah ketik tanpa bikin duplikat.
 * PASSWORD SENGAJA TIDAK PERNAH ikut diperbarui (keputusan eksplisit --
 * user lama mungkin sudah ganti password sendiri). Email yang terdaftar
 * di tenant LAIN tetap dilewati diam-diam (skip_duplicate) -- TIDAK
 * PERNAH ditawarkan update lintas tenant.
 */
class NewUserRowsImport implements ToCollection, WithHeadingRow
{
    use AssignsUserRoles;
    use Importable;

    /**
     * @var list<string>
     */
    private const UPDATABLE_FIELDS = ['nama', 'telp', 'role', 'outlet_id', 'dept_id', 'status'];

    /**
     * @var array<string, string>
     */
    private const FIELD_LABELS = [
        'nama' => 'Nama',
        'telp' => 'Telp',
        'role' => 'Role',
        'outlet_id' => 'Outlet',
        'dept_id' => 'Departemen',
        'status' => 'Status Akun',
    ];

    private Collection $rows;

    public function collection(Collection $rows): void
    {
        $this->rows = $rows;
    }

    /**
     * @return array{rows: list<array<string, mixed>>, total: int, creatable: int, duplicate_update: int, skip_duplicate: int, error: int}
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
            'creatable' => count(array_filter($out, fn ($r) => $r['row_status'] === 'creatable')),
            'duplicate_update' => count(array_filter($out, fn ($r) => $r['row_status'] === 'duplicate_update')),
            'duplicate_no_change' => count(array_filter($out, fn ($r) => $r['row_status'] === 'duplicate_no_change')),
            'skip_duplicate' => count(array_filter($out, fn ($r) => $r['row_status'] === 'skip_duplicate')),
            'error' => count(array_filter($out, fn ($r) => $r['row_status'] === 'error')),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function evaluateRow(Collection $row, int $tenantId): array
    {
        $name = trim((string) ($row['nama'] ?? ''));
        $email = Str::lower(trim((string) ($row['email'] ?? '')));
        $phone = trim((string) ($row['telp'] ?? '')) ?: null;
        $password = (string) ($row['password_nokom'] ?? ''); // JANGAN cast int/numeric -- buang nol di depan.
        $role = strtoupper(trim((string) ($row['role'] ?? '')));
        $outletId = $row['outlet_id'] !== null && $row['outlet_id'] !== '' ? (int) $row['outlet_id'] : null;
        $deptId = $row['dept_id'] !== null && $row['dept_id'] !== '' ? (int) $row['dept_id'] : null;
        $statusRaw = strtoupper(trim((string) ($row['status'] ?? 'ACTIVE')));
        $accountStatus = $statusRaw === 'INACTIVE' ? 'INACTIVE' : 'ACTIVE';

        $base = [
            'nama' => $name,
            'email' => $email,
            'telp' => $phone,
            'password_nokom' => $password,
            'role' => $role,
            'outlet_id' => $outletId,
            'dept_id' => $deptId,
            'status' => $accountStatus,
            'password_short_warning' => $password !== '' && strlen($password) < 6,
        ];

        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
            return $base + ['row_status' => 'error', 'message' => 'Email atau password tidak valid.'];
        }

        $existingSameTenant = User::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereRaw('LOWER(email) = ?', [$email])
            ->with('roles')
            ->first();

        if (! $existingSameTenant && User::withoutGlobalScopes()->whereRaw('LOWER(email) = ?', [$email])->exists()) {
            // Email sudah dipakai, tapi di tenant LAIN -- tidak pernah
            // ditawarkan update lintas tenant, cukup dilewati diam-diam.
            return $base + ['row_status' => 'skip_duplicate', 'message' => 'Email sudah terdaftar (tenant lain), dilewati.'];
        }

        $validator = validator($base, [
            'role' => ['required', Rule::exists('roles', 'name')],
            'outlet_id' => [
                in_array($role, UserController::OUTLET_SCOPED_ROLES, true) ? 'required' : 'nullable',
                Rule::exists('outlets', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId)->where('status', 'ACTIVE')),
            ],
            'dept_id' => ['nullable', Rule::exists('departments', 'id')->where('tenant_id', $tenantId)],
        ]);

        if ($validator->fails()) {
            return $base + ['row_status' => 'error', 'message' => collect($validator->errors()->all())->implode(' ')];
        }

        if ($existingSameTenant) {
            $changes = $this->diffAgainstExisting($existingSameTenant, $base);

            if ($changes === []) {
                return $base + ['row_status' => 'duplicate_no_change', 'message' => 'Data sama persis dengan yang sudah ada, tidak ada yang diperbarui.'];
            }

            return $base + [
                'row_status' => 'duplicate_update',
                'message' => 'Email sudah terdaftar -- '.count($changes).' field akan diperbarui.',
                'changes' => $changes,
            ];
        }

        return $base + ['row_status' => 'creatable', 'message' => null];
    }

    /**
     * @param  array<string, mixed>  $incoming
     * @return array<string, array{label: string, from: mixed, to: mixed}>
     */
    private function diffAgainstExisting(User $existing, array $incoming): array
    {
        $current = [
            'nama' => $existing->name,
            'telp' => $existing->phone,
            'role' => strtoupper((string) ($existing->roles->first()?->name ?? '')),
            'outlet_id' => $existing->outlet_id,
            'dept_id' => $existing->department_id,
            'status' => strtoupper((string) $existing->status),
        ];

        $changes = [];
        foreach (self::UPDATABLE_FIELDS as $field) {
            if ($current[$field] != $incoming[$field]) { // perbandingan longgar: null vs '' vs 0 dianggap sama
                $changes[$field] = [
                    'label' => self::FIELD_LABELS[$field],
                    'from' => $current[$field],
                    'to' => $incoming[$field],
                ];
            }
        }

        return $changes;
    }

    /**
     * Validasi ULANG semua field di server -- jangan percaya data dari
     * client mentah-mentah, preview cuma bantuan tampilan.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return array{created: int, updated: int, skipped: int, errors: list<string>}
     */
    public function applyChanges(array $rows, int $tenantId, User $actingUser): array
    {
        $created = 0;
        $updated = 0;
        $skipped = 0;
        $errors = [];

        foreach ($rows as $row) {
            $status = $row['row_status'] ?? null;

            if ($status === 'creatable') {
                try {
                    $this->createUser($row, $tenantId, $actingUser);
                    $created++;
                } catch (Throwable $e) {
                    $skipped++;
                    $errors[] = ($row['email'] ?? '?').': '.$e->getMessage();
                }

                continue;
            }

            if ($status === 'duplicate_update') {
                try {
                    $this->updateUser($row, $tenantId, $actingUser);
                    $updated++;
                } catch (Throwable $e) {
                    $skipped++;
                    $errors[] = ($row['email'] ?? '?').': '.$e->getMessage();
                }

                continue;
            }

            $skipped++;
        }

        return ['created' => $created, 'updated' => $updated, 'skipped' => $skipped, 'errors' => $errors];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function createUser(array $row, int $tenantId, User $actingUser): void
    {
        $email = Str::lower(trim((string) ($row['email'] ?? '')));
        $role = strtoupper(trim((string) ($row['role'] ?? '')));

        if ($email === '' || User::withoutGlobalScopes()->whereRaw('LOWER(email) = ?', [$email])->exists()) {
            throw new \RuntimeException('Email tidak valid atau sudah terdaftar saat diterapkan.');
        }

        $validated = $this->validateRow($row, $tenantId, $email, $role);

        $this->assertMayAssignRole($actingUser, $validated['role']);

        $user = User::withoutGlobalScopes()->create([
            'tenant_id' => $tenantId,
            'outlet_id' => $validated['outlet_id'] ?? null,
            'department_id' => $validated['dept_id'] ?? null,
            'name' => $validated['nama'],
            'email' => $validated['email'],
            // RAW, bukan Hash::make() -- lihat catatan di atas class.
            'password' => $validated['password_nokom'],
            'phone' => $row['telp'] ?? null,
            'status' => ($row['status'] ?? 'ACTIVE') === 'INACTIVE' ? 'INACTIVE' : 'ACTIVE',
            'email_verified_at' => now(),
        ]);
        $user->assignRole($validated['role']);
    }

    /**
     * Update HANYA field non-password (nama/telp/role/outlet/dept/status).
     * Password user yang sudah ada TIDAK PERNAH disentuh oleh import ini.
     *
     * @param  array<string, mixed>  $row
     */
    private function updateUser(array $row, int $tenantId, User $actingUser): void
    {
        $email = Str::lower(trim((string) ($row['email'] ?? '')));
        $role = strtoupper(trim((string) ($row['role'] ?? '')));

        // Dicari ULANG by email+tenant (bukan percaya ID dari client) --
        // kalau emailnya sudah pindah tenant/dihapus sejak preview, baris
        // ini gagal rapi, bukan diam-diam update user yang salah.
        $existing = User::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereRaw('LOWER(email) = ?', [$email])
            ->first();

        if (! $existing) {
            throw new \RuntimeException('User tidak ditemukan di tenant ini saat diterapkan (mungkin sudah berubah sejak preview).');
        }

        $validated = $this->validateRow($row, $tenantId, $email, $role);

        $this->assertMayAssignRole($actingUser, $validated['role']);

        $existing->update([
            'name' => $validated['nama'],
            'outlet_id' => $validated['outlet_id'] ?? null,
            'department_id' => $validated['dept_id'] ?? null,
            'phone' => $row['telp'] ?? null,
            'status' => ($row['status'] ?? 'ACTIVE') === 'INACTIVE' ? 'INACTIVE' : 'ACTIVE',
        ]);
        $existing->syncRoles([$validated['role']]);
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function validateRow(array $row, int $tenantId, string $email, string $role): array
    {
        return validator([
            'nama' => trim((string) ($row['nama'] ?? '')),
            'email' => $email,
            'password_nokom' => (string) ($row['password_nokom'] ?? ''),
            'role' => $role,
            'outlet_id' => $row['outlet_id'] ?? null,
            'dept_id' => $row['dept_id'] ?? null,
        ], [
            'nama' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email'],
            'password_nokom' => ['required', 'string'],
            'role' => ['required', Rule::exists('roles', 'name')],
            'outlet_id' => [
                in_array($role, UserController::OUTLET_SCOPED_ROLES, true) ? 'required' : 'nullable',
                Rule::exists('outlets', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId)->where('status', 'ACTIVE')),
            ],
            'dept_id' => ['nullable', Rule::exists('departments', 'id')->where('tenant_id', $tenantId)],
        ])->validate();
    }
}
