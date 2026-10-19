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
 * (creatable/skip_duplicate/error) -- SENGAJA BUKAN 'status', supaya
 * tidak bentrok dgn kolom Excel "Status" (ACTIVE/INACTIVE akun itu
 * sendiri). Dulu pernah collide pakai nama yg sama -- `$base +
 * ['status' => ...]` diam-diam TIDAK menimpa nilai yang sudah ada
 * (operator `+` array PHP keep nilai kiri), bikin semua baris salah
 * dikira berstatus akun, bukan status hasil evaluasi.
 */
class NewUserRowsImport implements ToCollection, WithHeadingRow
{
    use AssignsUserRoles;
    use Importable;

    private Collection $rows;

    public function collection(Collection $rows): void
    {
        $this->rows = $rows;
    }

    /**
     * @return array{rows: list<array<string, mixed>>, total: int, creatable: int, skip_duplicate: int, error: int}
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

        if (User::withoutGlobalScopes()->whereRaw('LOWER(email) = ?', [$email])->exists()) {
            return $base + ['row_status' => 'skip_duplicate', 'message' => 'Email sudah terdaftar, dilewati.'];
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

        return $base + ['row_status' => 'creatable', 'message' => null];
    }

    /**
     * Validasi ULANG semua field di server -- jangan percaya data dari
     * client mentah-mentah, preview cuma bantuan tampilan.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return array{created: int, skipped: int, errors: list<string>}
     */
    public function applyChanges(array $rows, int $tenantId, User $actingUser): array
    {
        $created = 0;
        $skipped = 0;
        $errors = [];

        foreach ($rows as $row) {
            if (($row['row_status'] ?? null) !== 'creatable') {
                $skipped++;
                continue;
            }

            $email = Str::lower(trim((string) ($row['email'] ?? '')));
            $role = strtoupper(trim((string) ($row['role'] ?? '')));

            try {
                if ($email === '' || User::withoutGlobalScopes()->whereRaw('LOWER(email) = ?', [$email])->exists()) {
                    throw new \RuntimeException('Email tidak valid atau sudah terdaftar saat diterapkan.');
                }

                $validated = validator([
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
                $created++;
            } catch (Throwable $e) {
                $skipped++;
                $errors[] = ($row['email'] ?? '?').': '.$e->getMessage();
            }
        }

        return ['created' => $created, 'skipped' => $skipped, 'errors' => $errors];
    }
}
