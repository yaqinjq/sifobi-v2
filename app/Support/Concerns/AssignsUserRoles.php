<?php

namespace App\Support\Concerns;

use App\Models\User;

/**
 * Dipakai bersama oleh UserController (form create/edit manual) dan
 * NewUserRowsImport (import massal lewat Excel) -- SATU sumber
 * kebenaran utk siapa boleh memberikan role SUPER_ADMIN, supaya kedua
 * jalur tidak pernah divergen.
 */
trait AssignsUserRoles
{
    /**
     * Validasi 'role' di form request cuma cek role itu ADA (Rule::exists),
     * tidak cek siapa yang boleh MEMBERIKANnya -- tanpa pengecekan ini,
     * siapa pun dengan manage_users (bukan cuma SUPER_ADMIN) bisa assign
     * role SUPER_ADMIN ke user manapun, termasuk ke akun sendiri (eskalasi
     * privilese instan, tidak ada pengecekan hierarki role sama sekali di
     * codebase ini). Cegah khusus untuk SUPER_ADMIN: cuma SUPER_ADMIN yang
     * sudah ada yang boleh assign role SUPER_ADMIN ke siapa pun.
     */
    protected function assertMayAssignRole(User $actingUser, string $roleName): void
    {
        if (strtoupper($roleName) !== 'SUPER_ADMIN') {
            return;
        }

        abort_unless(
            $actingUser->hasRole('SUPER_ADMIN'),
            403,
            'Hanya SUPER_ADMIN yang boleh memberikan role SUPER_ADMIN.'
        );
    }
}
