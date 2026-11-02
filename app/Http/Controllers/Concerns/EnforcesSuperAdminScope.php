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
 * Non-superadmin WAJIB punya outlet (TUNGGAL -- lihat effectiveOutletId()
 * di bawah soal akun multi-outlet) DAN department_id yang valid di
 * akunnya. Kalau salah satu kosong (mis. PIC_OUTLET/MANAGER_AREA yang
 * desainnya tidak terikat 1 departemen), scope-nya dianggap TIDAK ADA --
 * fail closed (tidak boleh akses apa pun di modul ini), BUKAN "lihat
 * semua karena belum di-assign". Ini konsekuensi kebijakan yang disengaja
 * (lihat laporan workflow yang terblokir); bukan bug.
 *
 * Lanjutan akses multi-outlet (lihat commit 9ffad0a, HasOutletAccess):
 * modul ini SENGAJA tetap fail-closed untuk akun yang punya BANYAK
 * outlet -- soleOutletId() balik null kalau >1, PERSIS seperti akun
 * tanpa outlet sama sekali, supaya tidak mengulang bug fail-closed yang
 * pernah terjadi di modul ini (diam-diam pilih outlet pertama/legacy).
 * Yang membedakan: akun multi-outlet BISA keluar dari kondisi itu dengan
 * memilih 1 outlet secara eksplisit lewat
 * SpoilWasteController::selectOutlet()/setOutlet() (disimpan di session,
 * bukan di database -- jadi tidak permanen, ganti outlet aktif kapan pun
 * lewat halaman yang sama), lihat effectiveOutletId().
 */
trait EnforcesSuperAdminScope
{
    private function isSuperAdmin(Request $request): bool
    {
        return (bool) $request->user()?->hasRole('SUPER_ADMIN');
    }

    /**
     * Resolusi outlet TUNGGAL yang berlaku untuk modul Spoil & Waste pada
     * request ini. Urutan: soleOutletId() (akun lama 1-outlet ATAU akun
     * pivot dengan pas 1 baris -- 0 perubahan perilaku) -> kalau null DAN
     * akun ini memang multi-outlet, coba outlet yang sudah dipilih
     * eksplisit di session (session('spoil_active_outlet_id')) ASALKAN
     * masih ada di accessibleOutletIds() akun ini saat ini (bukan sekadar
     * dipercaya mentah-mentah -- outlet assignment user bisa berubah
     * setelah dipilih) -> null kalau tidak ada satupun yang valid (fail
     * closed, sama seperti akun tanpa outlet).
     */
    private function effectiveSpoilOutletId(Request $request): ?int
    {
        $user = $request->user();

        if (! $user) {
            return null;
        }

        $sole = $user->soleOutletId();

        if ($sole !== null) {
            return $sole;
        }

        if (! $user->hasMultipleOutlets()) {
            return null;
        }

        $sessionOutletId = (int) session('spoil_active_outlet_id', 0);

        if ($sessionOutletId > 0 && in_array($sessionOutletId, $user->accessibleOutletIds(), true)) {
            return $sessionOutletId;
        }

        return null;
    }

    /**
     * True kalau user non-superadmin tsb punya outlet (lihat
     * effectiveSpoilOutletId()) DAN department_id yang BENAR-BENAR SAH --
     * bukan cuma kolomnya terisi (ID tidak null), tapi juga outlet/
     * departemen itu nyata ADA dan milik tenant yang sama dengan user
     * tsb. Kalau akun punya ID "nyasar" (mis. data korup, atau outlet/
     * departemen sudah dihapus/pindah tenant), tetap dianggap TIDAK
     * punya scope yang sah -- fail closed, bukan dianggap valid cuma
     * karena kolomnya tidak null.
     */
    private function hasValidSpoilScope(Request $request): bool
    {
        $user = $request->user();
        $outletId = $this->effectiveSpoilOutletId($request);

        if (! $outletId || ! $user?->department_id) {
            return false;
        }

        $tenantId = (int) $user->tenant_id;

        $outletValid = Outlet::query()
            ->where('tenant_id', $tenantId)
            ->where('id', $outletId)
            ->exists();

        $departmentValid = Department::query()
            ->where('tenant_id', $tenantId)
            ->where('id', $user->department_id)
            ->exists();

        return $outletValid && $departmentValid;
    }
}
