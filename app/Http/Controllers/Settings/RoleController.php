<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleController extends Controller
{
    public function index(): View
    {
        $teamId = app(PermissionRegistrar::class)->getPermissionsTeamId();

        $roles = Role::with('permissions')
            ->withCount(['users'])
            ->where('team_id', $teamId)
            ->orderBy('name')
            ->get();

        return view('settings.roles.index', [
            'roles'  => $roles,
            'groups' => $this->permissionGroups(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $teamId = app(PermissionRegistrar::class)->getPermissionsTeamId();

        $data = $request->validate([
            'name' => [
                'required', 'string', 'max:60', 'regex:/^[A-Z0-9_]+$/',
                Rule::unique('roles', 'name')->where('guard_name', 'web')->where('team_id', $teamId),
            ],
        ], [
            'name.regex' => 'Nama role hanya boleh huruf kapital, angka, dan underscore (contoh: STAFF_PASTRY).',
        ]);

        $role = Role::create(['name' => $data['name'], 'guard_name' => 'web', 'team_id' => $teamId]);

        activity('user')
            ->causedBy(request()->user())
            ->performedOn($role)
            ->event('role_created')
            ->log("Role {$role->name} dibuat");

        return redirect()
            ->route('settings.roles.edit', $role)
            ->with('success', "Role {$role->name} berhasil dibuat. Atur permission-nya di bawah.");
    }

    public function edit(Role $role): View
    {
        $this->authorizeRole($role);

        $role->load('permissions');

        return view('settings.roles.edit', [
            'role'   => $role,
            'groups' => $this->permissionGroups(),
        ]);
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        $this->authorizeRole($role);

        $request->validate([
            'permissions'   => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::exists('permissions', 'name')->where('guard_name', 'web')],
        ]);

        $oldPermissions = $role->permissions->pluck('name')->toArray();
        $newPermissions = $request->input('permissions', []);

        $role->syncPermissions($newPermissions);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $added = array_values(array_diff($newPermissions, $oldPermissions));
        $removed = array_values(array_diff($oldPermissions, $newPermissions));

        if ($added || $removed) {
            activity('user')
                ->causedBy($request->user())
                ->performedOn($role)
                ->withProperties(['added' => $added, 'removed' => $removed])
                ->event('permissions_changed')
                ->log("Permission role {$role->name} diubah");
        }

        return back()->with('success', "Permission role {$role->name} berhasil disimpan.");
    }

    /**
     * Role:: route-model binding TIDAK otomatis di-scope per tenant (beda
     * dari model lain di app ini yang pakai TenantScope) — Spatie tidak
     * mendaftarkan global scope apapun di model Role-nya sendiri, cuma
     * relasi lewat user (hasRole/can) yang otomatis ikut team_id. Jadi
     * wajib dicek manual di sini, supaya tenant lain tidak bisa
     * lihat/ubah/hapus role tenant lain lewat tebak-tebak ID di URL.
     */
    private function authorizeRole(Role $role): void
    {
        $teamId = app(PermissionRegistrar::class)->getPermissionsTeamId();

        abort_unless((int) $role->team_id === (int) $teamId, 404);
    }

    public function destroy(Role $role): RedirectResponse
    {
        $this->authorizeRole($role);

        $userCount = User::role($role->name)->count();

        if ($userCount > 0) {
            return back()->with('error',
                "Role {$role->name} tidak bisa dihapus — masih ada {$userCount} user yang memakai role ini. Pindahkan user terlebih dahulu.");
        }

        $name = $role->name;
        $role->delete();

        activity('user')
            ->causedBy(request()->user())
            ->withProperties(['role' => $name])
            ->event('role_deleted')
            ->log("Role {$name} dihapus");

        return redirect()
            ->route('settings.roles.index')
            ->with('success', "Role {$name} berhasil dihapus.");
    }

    /**
     * Permission grouped by module for display in the matrix.
     * Format: 'Group Label' => [ 'permission_name' => 'label' ]
     *
     * @return array<string, array<string, string>>
     */
    public function permissionGroups(): array
    {
        return [
            'Dashboard' => [
                'view_dashboard' => 'Lihat Dashboard',
            ],
            'Master Data' => [
                'view_master_data'  => 'Lihat Master Data',
                'manage_items'      => 'Kelola Item/Bahan',
                'manage_units'      => 'Kelola Satuan',
                'export_master_data' => 'Export Data',
                'import_master_data' => 'Import Data',
            ],
            'Stok & Gudang' => [
                'view_inventory'    => 'Lihat Stok',
                'view_stock_balance' => 'Lihat Saldo Stok',
                'input_open_stock'  => 'Input Open Stock',
                'post_open_stock'   => 'Posting Open Stock',
            ],
            'Transfer Stok' => [
                'create_stock_transfers'  => 'Buat Transfer',
                'approve_stock_transfers' => 'Approve Transfer',
            ],
            'Opname' => [
                'input_opname'   => 'Input Opname',
                'approve_opname' => 'Approve Opname',
            ],
            'Spoil & Waste' => [
                'record_spoil'       => 'Catat Spoil',
                'input_spoil_waste'  => 'Input Spoil/Waste',
                'approve_spoil'      => 'Approve Spoil',
                'approve_spoil_waste' => 'Approve Waste',
            ],
            'Penerimaan Barang (GR)' => [
                'view_goods_receipt'    => 'Lihat GR',
                'create_goods_receipt'  => 'Buat GR',
                'submit_goods_receipt'  => 'Submit GR',
                'input_receiving'       => 'Input Penerimaan',
                'approve_receiving'     => 'Approve Penerimaan',
                'approve_goods_receipt' => 'Approve GR',
                'reject_goods_receipt'  => 'Tolak GR',
            ],
            'Purchase Order (PO)' => [
                'create_po'   => 'Buat PO',
                'approve_po'  => 'Approve PO',
                'view_all_po' => 'Lihat PO Semua Dept.',
            ],
            'Menu & Resep' => [
                'manage_recipes'  => 'Kelola Menu & Resep',
                'approve_recipes' => 'Approve Resep',
            ],
            'POS/Kasir' => [
                'operate_pos'        => 'Operasikan Kasir',
                'manage_pos_layout'  => 'Kelola Denah Meja',
                'view_pos_reports'   => 'Lihat Laporan POS',
                'void_pos_order'     => 'Void Order POS',
                'approve_pos_shift'  => 'Approve Shift Kasir',
            ],
            'Laporan' => [
                'view_reports'     => 'Lihat Laporan',
                'view_all_reports' => 'Laporan Semua Outlet',
            ],
            'Pengaturan Sistem' => [
                'manage_settings'         => 'Pengaturan Umum',
                'manage_brands_outlets'   => 'Kelola Outlet',
                'manage_integrations'     => 'Integrasi API',
                'manage_stock_configs'    => 'Konfigurasi Stok',
                'manage_calendar_events'  => 'Kalender Acara',
            ],
            'Manajemen User & Role' => [
                'manage_users' => 'Kelola User & Role',
            ],
            'Log Aktivitas & Kepatuhan' => [
                'view_audit_log' => 'Lihat Log Aktivitas',
            ],
            'Admin / Core' => [
                'manage_core' => 'Akses Core System',
            ],
        ];
    }
}
