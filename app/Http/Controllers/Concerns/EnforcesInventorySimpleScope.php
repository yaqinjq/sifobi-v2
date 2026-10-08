<?php

namespace App\Http\Controllers\Concerns;

use App\Modules\Core\Models\Department;
use App\Modules\Core\Models\Outlet;
use Illuminate\Http\Request;

/**
 * Kebijakan akses modul Inventory Simple: HANYA role SUPER_ADMIN eksplisit
 * (dikenali lewat hasRole(), bukan dari assignment kosong) boleh memilih
 * outlet/departemen bebas -- dan itu PUN tetap wajib dalam tenant akun yang
 * sah. Non-superadmin WAJIB punya outlet_id DAN department_id yang
 * benar-benar ada dan milik tenant yang sama (bukan cuma kolom tidak null)
 * -- kalau tidak, fail closed (tidak ada scope yang sah), BUKAN "lihat
 * semua karena belum di-assign".
 *
 * SENGAJA ditulis independen di modul Inventory Simple ini (BUKAN meng-
 * import/reuse trait serupa dari branch fix/spoil-waste-department-scope)
 * -- branch itu belum disetujui/di-merge, jadi modul ini tidak boleh
 * bergantung padanya sama sekali.
 */
trait EnforcesInventorySimpleScope
{
    private function isSuperAdmin(Request $request): bool
    {
        return (bool) $request->user()?->hasRole('SUPER_ADMIN');
    }

    /**
     * True kalau user non-superadmin punya outlet_id DAN department_id yang
     * benar-benar sah -- ada di tabel outlets/departments dan milik tenant
     * yang sama dengan user tsb, bukan cuma kolomnya tidak null.
     */
    private function hasValidInventorySimpleScope(Request $request): bool
    {
        $user = $request->user();

        if (! $user?->outlet_id || ! $user?->department_id) {
            return false;
        }

        $tenantId = (int) $user->tenant_id;

        $outletValid = Outlet::query()
            ->where('tenant_id', $tenantId)
            ->where('id', $user->outlet_id)
            ->exists();

        $departmentValid = Department::query()
            ->where('tenant_id', $tenantId)
            ->where('id', $user->department_id)
            ->exists();

        return $outletValid && $departmentValid;
    }

    /**
     * Resolusi outlet_id + department_id yang BERLAKU untuk request ini --
     * non-superadmin SELALU dari akun sendiri (input client diabaikan
     * total), superadmin boleh pilih bebas TAPI tetap divalidasi dalam
     * tenant yang sama persis.
     *
     * @return array{0: int, 1: int}|null null kalau tidak ada scope yang sah.
     */
    private function resolveInventorySimpleScope(Request $request): ?array
    {
        $user = $request->user();
        $tenantId = (int) $user->tenant_id;

        if ($this->isSuperAdmin($request)) {
            $outletId = (int) $request->input('outlet_id', 0);
            $departmentId = (int) $request->input('department_id', 0);

            if ($outletId <= 0 || $departmentId <= 0) {
                return null;
            }

            $outletValid = Outlet::query()->where('tenant_id', $tenantId)->where('id', $outletId)->exists();
            $departmentValid = Department::query()->where('tenant_id', $tenantId)->where('id', $departmentId)->exists();

            if (! $outletValid || ! $departmentValid) {
                return null;
            }

            return [$outletId, $departmentId];
        }

        if (! $this->hasValidInventorySimpleScope($request)) {
            return null;
        }

        return [(int) $user->outlet_id, (int) $user->department_id];
    }
}
