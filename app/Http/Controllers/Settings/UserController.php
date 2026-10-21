<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Concerns\HasBulkAction;
use App\Http\Controllers\Concerns\HasPerPageSelector;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\BulkChangeOutletUserRequest;
use App\Http\Requests\Settings\BulkDeleteUserRequest;
use App\Http\Requests\Settings\BulkToggleUserStatusRequest;
use App\Imports\DeactivateTrialUserRowsImport;
use App\Imports\NewUserRowsImport;
use App\Imports\UserImport;
use App\Models\User;
use App\Modules\Core\Models\Department;
use App\Modules\Core\Models\Outlet;
use App\Support\Concerns\AssignsUserRoles;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class UserController extends Controller
{
    use AssignsUserRoles;
    use HasBulkAction;
    use HasPerPageSelector;

    /**
     * Role yang WAJIB terikat ke 1 outlet spesifik. Kalau outlet_id kosong
     * untuk role ini, user jadi dianggap "tidak terikat outlet" di semua
     * halaman (Kartu Stok, Opname, Open Stock, dst) -- sama seperti
     * SUPER_ADMIN/MANAGER_AREA -- sehingga bisa melihat/memilih SEMUA
     * outlet tenant, bukan cuma outlet-nya sendiri.
     *
     * @var list<string>
     */
    public const OUTLET_SCOPED_ROLES = ['PIC_OUTLET', 'STAFF_BAR', 'STAFF_KITCHEN', 'STAFF_SERVICE', 'STAFF_GUDANG'];

    public function index(Request $request): View
    {
        $tenantId = (int) $request->user()->tenant_id;
        [$perPage, $perPageOptions] = $this->perPageAndOptions($request, 20);
        $search = $request->get('q');
        $roleFilter = $request->get('role');
        $statusFilter = $request->get('status');
        $outletFilter = $request->get('outlet_id');
        $statusValue = $this->statusToDatabase($statusFilter);

        $users = User::query()
            ->where('tenant_id', $tenantId)
            ->with(['roles', 'outlet'])
            ->when($search, fn ($query) => $query->where(function ($query) use ($search): void {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            }))
            ->when($roleFilter, fn ($query) => $query->whereHas('roles', fn ($roleQuery) => $roleQuery->where('name', $roleFilter)))
            ->when($statusValue, fn ($query) => $query->where('status', $statusValue))
            ->when($outletFilter, fn ($query) => $query->where('outlet_id', $outletFilter))
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();

        return view('settings.users.index', [
            'users' => $users,
            'roles' => $this->roles(),
            'outlets' => $this->outlets($tenantId),
            'search' => $search,
            'roleFilter' => $roleFilter,
            'statusFilter' => $statusFilter,
            'outletFilter' => $outletFilter,
            'perPage' => $perPage,
            'perPageOptions' => $perPageOptions,
        ]);
    }

    public function create(Request $request): View
    {
        $tenantId = (int) $request->user()->tenant_id;

        return view('settings.users.create', [
            'user'        => new User(['status' => 'ACTIVE']),
            'roles'       => $this->roles(),
            'outlets'     => $this->outlets($tenantId),
            'departments' => $this->departments($tenantId),
            'extraPerms'  => $this->extraPermissions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $tenantId = (int) $request->user()->tenant_id;
        $data = $this->validated($request, $tenantId);
        $this->assertMayAssignRole($request->user(), $data['role']);

        DB::transaction(function () use ($data, $tenantId, $request): void {
            $user = User::withoutGlobalScopes()->create([
                'tenant_id'     => $tenantId,
                'outlet_id'     => $data['outlet_id'] ?? null,
                'department_id' => $data['department_id'] ?? null,
                'name'          => $data['name'],
                'email'         => $data['email'],
                'password'      => $data['password'],
                'phone'         => $data['phone'] ?? null,
                'status'        => $this->statusToDatabase($data['status']),
                'email_verified_at' => now(),
            ]);

            $user->assignRole($data['role']);
            activity('user')
                ->causedBy($request->user())
                ->performedOn($user)
                ->withProperties(['role' => $data['role']])
                ->event('role_assigned')
                ->log("Role {$data['role']} diberikan ke user {$user->name}");

            $this->syncExtraPermissions($user, $request->input('extra_permissions', []));
        });

        return redirect()
            ->route('settings.users.index')
            ->with('success', "User {$data['name']} berhasil ditambahkan.");
    }

    public function edit(Request $request, User $user): View
    {
        $this->authorizeUser($request, $user);
        $tenantId = (int) $request->user()->tenant_id;

        $user->load(['roles', 'outlet', 'permissions']);

        return view('settings.users.edit', [
            'user'        => $user,
            'roles'       => $this->roles(),
            'outlets'     => $this->outlets($tenantId),
            'departments' => $this->departments($tenantId),
            'extraPerms'  => $this->extraPermissions(),
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $this->authorizeUser($request, $user);
        $tenantId = (int) $request->user()->tenant_id;
        $data = $this->validated($request, $tenantId, $user);
        $this->assertMayAssignRole($request->user(), $data['role']);

        DB::transaction(function () use ($data, $user, $request): void {
            $payload = [
                'name'          => $data['name'],
                'email'         => $data['email'],
                'outlet_id'     => $data['outlet_id'] ?? null,
                'department_id' => $data['department_id'] ?? null,
                'phone'         => $data['phone'] ?? null,
                'status'        => $this->statusToDatabase($data['status']),
            ];

            if (filled($data['password'] ?? null)) {
                $payload['password'] = $data['password'];
            }

            $oldRoles = $user->getRoleNames()->toArray();

            $user->update($payload);
            $user->syncRoles([$data['role']]);

            if ($oldRoles !== [$data['role']]) {
                activity('user')
                    ->causedBy($request->user())
                    ->performedOn($user)
                    ->withProperties(['old_roles' => $oldRoles, 'new_role' => $data['role']])
                    ->event('role_changed')
                    ->log("Role user {$user->name} diubah dari ".(implode(', ', $oldRoles) ?: '-')." ke {$data['role']}");
            }

            $this->syncExtraPermissions($user, $request->input('extra_permissions', []));
        });

        return redirect()
            ->route('settings.users.index')
            ->with('success', "User {$user->name} berhasil diperbarui.");
    }

    public function toggleStatus(Request $request, User $user): RedirectResponse
    {
        $this->authorizeUser($request, $user);

        if ((int) $user->id === (int) $request->user()->id) {
            return back()->with('error', 'Tidak bisa menonaktifkan akun Anda sendiri.');
        }

        $nextStatus = strtoupper((string) $user->status) === 'ACTIVE' ? 'INACTIVE' : 'ACTIVE';

        $user->update(['status' => $nextStatus]);

        $label = $nextStatus === 'ACTIVE' ? 'diaktifkan' : 'dinonaktifkan';

        return back()->with('success', "User {$user->name} berhasil {$label}.");
    }

    public function bulkToggleStatus(BulkToggleUserStatusRequest $request): RedirectResponse
    {
        $tenantId = (int) $request->user()->tenant_id;
        $targetStatus = $request->string('status')->upper()->toString();
        $currentStatus = $targetStatus === 'ACTIVE' ? 'INACTIVE' : 'ACTIVE';

        $users = User::query()
            ->whereIn('id', $request->input('ids', []))
            ->where('tenant_id', $tenantId)
            ->where('id', '!=', $request->user()->id)
            ->where('status', $currentStatus)
            ->get();

        $result = $this->runBulkAction(
            $users,
            fn (User $user) => $user->update(['status' => $targetStatus]),
            fn (User $user) => $user->name
        );

        $label = $targetStatus === 'ACTIVE' ? 'diaktifkan' : 'dinonaktifkan';

        return $this->bulkActionRedirect('settings.users.index', $result, "{$result['processed']} user berhasil {$label}.");
    }

    public function bulkChangeOutlet(BulkChangeOutletUserRequest $request): RedirectResponse
    {
        $tenantId = (int) $request->user()->tenant_id;

        $users = User::query()
            ->whereIn('id', $request->input('ids', []))
            ->where('tenant_id', $tenantId)
            ->get();

        $result = $this->runBulkAction(
            $users,
            fn (User $user) => $user->update(['outlet_id' => $request->integer('outlet_id')]),
            fn (User $user) => $user->name
        );

        return $this->bulkActionRedirect('settings.users.index', $result, "{$result['processed']} user berhasil dipindah outlet.");
    }

    /**
     * Hapus PERMANEN -- sengaja dibatasi HANYA utk user yang belum pernah
     * login sama sekali (last_login_at null). User yang pernah login
     * kemungkinan py riwayat transaksi tertaut (goods_receipts.created_by
     * dkk) -- gunakan nonaktifkan, bukan hapus, utk akun semacam itu.
     * Baris yang lolos filter tapi tetap bentrok FK (restrictOnDelete)
     * otomatis diisolasi per-baris oleh runBulkAction(), tidak
     * menggagalkan baris lain.
     */
    public function bulkDelete(BulkDeleteUserRequest $request): RedirectResponse
    {
        $tenantId = (int) $request->user()->tenant_id;

        $users = User::query()
            ->whereIn('id', $request->input('ids', []))
            ->where('tenant_id', $tenantId)
            ->where('id', '!=', $request->user()->id)
            ->whereNull('last_login_at')
            ->get();

        $result = $this->runBulkAction(
            $users,
            function (User $user): void {
                $user->syncRoles([]);
                $user->delete();
            },
            fn (User $user) => $user->name
        );

        return $this->bulkActionRedirect('settings.users.index', $result, "{$result['processed']} user berhasil dihapus.");
    }

    public function importForm(): View
    {
        return view('settings.users.import');
    }

    /**
     * Baca file yang diupload, kembalikan preview JSON -- TIDAK menulis
     * apa pun ke database. Pola sama persis dgn
     * ImportExportController::previewPoTags().
     */
    public function importPreview(Request $request): JsonResponse
    {
        $tenantId = (int) $request->user()->tenant_id;
        $file = $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:10240'],
        ])['file'];

        $import = new UserImport();
        Excel::import($import, $file);

        return response()->json($import->preview($tenantId));
    }

    /**
     * Terapkan baris yang sudah direview admin di browser. Validasi ULANG
     * semua field di server (lihat NewUserRowsImport::applyChanges()) --
     * data dari client HANYA dipakai sbg daftar "baris mana yang mau
     * diproses", bukan dipercaya langsung.
     */
    public function importApply(Request $request): JsonResponse
    {
        $tenantId = (int) $request->user()->tenant_id;
        $validated = $request->validate([
            'new_users' => ['present', 'array'],
            'deactivations' => ['present', 'array'],
        ]);

        $newUsersResult = (new NewUserRowsImport())->applyChanges(
            $validated['new_users'],
            $tenantId,
            $request->user()
        );
        $deactivationResult = (new DeactivateTrialUserRowsImport())->applyChanges(
            $validated['deactivations'],
            $tenantId,
            $request->user()
        );

        return response()->json([
            'new_users' => $newUsersResult,
            'deactivations' => $deactivationResult,
        ]);
    }

    public function resetPassword(Request $request, User $user): RedirectResponse
    {
        $this->authorizeUser($request, $user);

        $temporaryPassword = 'Reset@'.random_int(10000, 99999);
        $user->update(['password' => $temporaryPassword]);

        activity('user')
            ->causedBy($request->user())
            ->performedOn($user)
            ->event('password_reset')
            ->log("Password user {$user->name} direset paksa oleh admin");

        return back()->with(
            'success',
            "Password {$user->name} direset. Password sementara: {$temporaryPassword}. Minta user segera mengganti password."
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, int $tenantId, ?User $user = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                Rule::unique('users', 'email')->ignore($user?->id),
            ],
            'password'      => [$user ? 'nullable' : 'required', 'string', 'min:8', 'confirmed'],
            'role'          => ['required', Rule::exists('roles', 'name')],
            'outlet_id'     => [
                in_array($request->input('role'), self::OUTLET_SCOPED_ROLES, true) ? 'required' : 'nullable',
                Rule::exists('outlets', 'id')->where(fn ($query) => $query
                    ->where('tenant_id', $tenantId)
                    ->where('status', 'ACTIVE')),
            ],
            'department_id' => [
                'nullable',
                Rule::exists('departments', 'id')->where('tenant_id', $tenantId),
            ],
            'phone'  => ['nullable', 'string', 'max:20'],
            'status' => ['required', Rule::in(['active', 'inactive', 'ACTIVE', 'INACTIVE'])],
        ], [
            'outlet_id.required' => 'Role ini terikat ke 1 outlet -- Outlet wajib dipilih, kalau tidak user ini akan bisa melihat SEMUA outlet.',
        ]);
    }

    /** Permission names that can be toggled per-user (on top of their role). */
    private function extraPermissions(): array
    {
        return [
            'view_all_po'          => 'Lihat PO semua departemen',
            'approve_po'           => 'Approve Purchase Order',
            'approve_goods_receipt' => 'Approve penerimaan barang',
            'view_all_reports'     => 'Lihat laporan semua outlet',
            'manage_items'         => 'Kelola Master Data Item',
        ];
    }

    private function syncExtraPermissions(User $user, array $grantedNames): void
    {
        $allowed = array_keys($this->extraPermissions());
        $toGrant = array_intersect($grantedNames, $allowed);
        $toRevoke = array_diff($allowed, $toGrant);

        $actuallyGranted = array_values(array_filter($toGrant, fn ($perm) => ! $user->hasDirectPermission($perm)));
        $actuallyRevoked = array_values(array_filter($toRevoke, fn ($perm) => $user->hasDirectPermission($perm)));

        if ($toGrant) {
            $user->givePermissionTo($toGrant);
        }

        foreach ($toRevoke as $perm) {
            if ($user->hasDirectPermission($perm)) {
                $user->revokePermissionTo($perm);
            }
        }

        if ($actuallyGranted || $actuallyRevoked) {
            activity('user')
                ->causedBy(request()->user())
                ->performedOn($user)
                ->withProperties(['granted' => $actuallyGranted, 'revoked' => $actuallyRevoked])
                ->event('permissions_changed')
                ->log("Permission tambahan user {$user->name} diubah");
        }
    }

    private function authorizeUser(Request $request, User $user): void
    {
        abort_unless((int) $user->tenant_id === (int) $request->user()->tenant_id, 403, 'Tidak berwenang.');
    }

    // assertMayAssignRole() dipindah ke trait App\Support\Concerns\AssignsUserRoles
    // supaya dipakai bersama oleh NewUserRowsImport (import massal) --
    // SATU sumber kebenaran, bukan disalin.

    private function statusToDatabase(?string $status): ?string
    {
        if ($status === null || $status === '') {
            return null;
        }

        return strtoupper($status) === 'INACTIVE' ? 'INACTIVE' : 'ACTIVE';
    }

    private function statusToForm(?string $status): string
    {
        return strtoupper((string) $status) === 'INACTIVE' ? 'inactive' : 'active';
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Role>
     */
    private function roles()
    {
        // Role:: tidak otomatis di-scope per tenant (lihat catatan yang
        // sama di RoleController::authorizeRole) — wajib filter manual.
        $teamId = app(PermissionRegistrar::class)->getPermissionsTeamId();

        return Role::query()->where('team_id', $teamId)->orderBy('name')->get();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Outlet>
     */
    private function outlets(int $tenantId)
    {
        return Outlet::query()
            ->where('tenant_id', $tenantId)
            ->where('status', 'ACTIVE')
            ->orderBy('name')
            ->get();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Department>
     */
    private function departments(int $tenantId)
    {
        return Department::query()
            ->where('tenant_id', $tenantId)
            ->orderBy('name')
            ->get();
    }
}
