<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Core\Models\Department;
use App\Modules\Core\Models\Outlet;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $tenantId = (int) $request->user()->tenant_id;
        $search = $request->get('q');
        $roleFilter = $request->get('role');
        $statusFilter = $request->get('status');
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
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('settings.users.index', [
            'users' => $users,
            'roles' => $this->roles(),
            'outlets' => $this->outlets($tenantId),
            'search' => $search,
            'roleFilter' => $roleFilter,
            'statusFilter' => $statusFilter,
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

    public function resetPassword(Request $request, User $user): RedirectResponse
    {
        $this->authorizeUser($request, $user);

        $temporaryPassword = 'Reset@'.random_int(10000, 99999);
        $user->update(['password' => $temporaryPassword]);

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
                'nullable',
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
        return Role::query()->orderBy('name')->get();
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
