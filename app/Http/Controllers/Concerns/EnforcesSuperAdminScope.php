<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\Request;

/**
 * Kebijakan tunggal untuk modul Spoil & Waste: HANYA role SUPER_ADMIN
 * (dikenali lewat mekanisme role resmi Spatie Permission -- `hasRole()`,
 * sama seperti dipakai RoleController/UserController) yang boleh lintas
 * tenant/outlet/departemen. Permission seperti `approve_spoil` adalah izin
 * MELAKUKAN approval, BUKAN izin melewati scope -- sengaja TIDAK dipakai
 * sebagai sinyal "boleh lihat semua" lagi (beda dari versi patch
 * sebelumnya yang memakai `can('approve_spoil')`).
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
     * yang valid (jadi punya scope yang sah untuk modul Spoil & Waste).
     */
    private function hasValidSpoilScope(Request $request): bool
    {
        $user = $request->user();

        return (bool) ($user?->outlet_id && $user?->department_id);
    }
}
