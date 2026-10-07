<?php

namespace App\Http\Controllers\Concerns;

use App\Modules\Core\Models\Department;
use App\Modules\Core\Models\Outlet;
use Illuminate\Http\Request;

/**
 * Kebijakan tunggal untuk modul Spoil & Waste: HANYA role SUPER_ADMIN
 * (dikenali lewat mekanisme role resmi Spatie Permission -- `hasRole()`,
 * sama seperti dipakai RoleController/UserController) yang boleh lintas
 * outlet/departemen DALAM tenant yang sama. Permission seperti
 * `approve_spoil` adalah izin MELAKUKAN approval, BUKAN izin melewati
 * scope -- sengaja TIDAK dipakai sebagai sinyal "boleh lihat semua" lagi
 * (beda dari versi patch sebelumnya yang memakai `can('approve_spoil')`).
 * SUPER_ADMIN TIDAK PERNAH boleh lintas tenant -- itu diperiksa EKSPLISIT
 * di tiap endpoint (lihat assertSpoilInScope()/applySpoilRecordScope() di
 * SpoilWasteController, dan filter tenant_id yang selalu dipasang duluan
 * di ReportController/import), bukan cuma mengandalkan TenantScope global
 * scope + route-model-binding secara implisit.
 *
 * Non-superadmin WAJIB punya outlet_id DAN department_id yang valid di
 * akunnya. Kalau salah satu kosong (mis. PIC_OUTLET/MANAGER_AREA yang
 * desainnya tidak terikat 1 departemen), scope-nya dianggap TIDAK ADA --
 * fail closed (tidak boleh akses apa pun di modul ini), BUKAN "lihat
 * semua karena belum di-assign". Ini konsekuensi kebijakan yang disengaja
 * (lihat laporan workflow yang terblokir); bukan bug.
 */
trait EnforcesSuperAdminScope
{
    private function isSuperAdmin(Request $request): bool
    {
        return (bool) $request->user()?->hasRole('SUPER_ADMIN');
    }

    /**
     * True kalau user non-superadmin tsb punya outlet_id DAN department_id
     * yang BENAR-BENAR SAH -- bukan cuma kolomnya terisi (ID tidak null),
     * tapi juga outlet/departemen itu nyata ADA dan milik tenant yang sama
     * dengan user tsb. Kalau akun punya ID "nyasar" (mis. data korup, atau
     * outlet/departemen sudah dihapus/pindah tenant), tetap dianggap TIDAK
     * punya scope yang sah -- fail closed, bukan dianggap valid cuma
     * karena kolomnya tidak null.
     */
    private function hasValidSpoilScope(Request $request): bool
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
}
