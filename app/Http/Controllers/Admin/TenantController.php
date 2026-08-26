<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantRoleSeeder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Spatie\Permission\PermissionRegistrar;

class TenantController extends Controller
{
    public function index(): View
    {
        $tenants = Tenant::query()->orderBy('name')->get();

        return view('admin.tenants.index', compact('tenants'));
    }

    public function create(): View
    {
        return view('admin.tenants.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:32', 'regex:/^[A-Z0-9_]+$/', Rule::unique('tenants', 'code')],
            'name' => ['required', 'string', 'max:150'],
            'admin_name' => ['required', 'string', 'max:150'],
            'admin_email' => ['required', 'email', Rule::unique('users', 'email')],
        ], [
            'code.regex' => 'Kode tenant hanya boleh huruf kapital, angka, dan underscore (contoh: KLIEN_A).',
        ]);

        $temporaryPassword = 'Welcome@'.random_int(10000, 99999);

        $admin = DB::transaction(function () use ($validated, $temporaryPassword) {
            $tenant = Tenant::create([
                'code' => $validated['code'],
                'name' => $validated['name'],
                'status' => 'ACTIVE',
            ]);

            app(TenantRoleSeeder::class)->seedForTenant($tenant->id);

            $admin = User::withoutGlobalScopes()->create([
                'tenant_id' => $tenant->id,
                'name' => $validated['admin_name'],
                'email' => $validated['admin_email'],
                'password' => $temporaryPassword,
                'status' => 'ACTIVE',
                'email_verified_at' => now(),
            ]);

            app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);
            $admin->assignRole('ADMIN');

            return $admin;
        });

        return redirect()
            ->route('admin.tenants.index')
            ->with(
                'success',
                "Tenant {$validated['name']} berhasil dibuat. Akun admin pertama: {$admin->email} — "
                ."Password sementara: {$temporaryPassword}. Sampaikan ke klien dan minta segera diganti."
            );
    }

    public function update(Request $request, Tenant $tenant): RedirectResponse
    {
        $request->merge([
            'subdomain' => $request->filled('subdomain') ? strtolower(trim($request->string('subdomain'))) : null,
            'custom_domain' => $request->filled('custom_domain') ? strtolower(trim($request->string('custom_domain'))) : null,
        ]);

        $validated = $request->validate([
            'subdomain' => [
                'nullable', 'string', 'max:63', 'regex:/^[a-z0-9]([a-z0-9-]*[a-z0-9])?$/',
                Rule::unique('tenants', 'subdomain')->ignore($tenant->id),
            ],
            'custom_domain' => [
                'nullable', 'string', 'max:191', 'regex:/^[a-z0-9.-]+\.[a-z]{2,}$/i',
                Rule::unique('tenants', 'custom_domain')->ignore($tenant->id),
            ],
        ], [
            'subdomain.regex' => 'Subdomain hanya boleh huruf kecil, angka, dan tanda minus (contoh: klien-a).',
            'custom_domain.regex' => 'Domain custom harus berupa domain valid (contoh: app.klien.com).',
        ]);

        $tenant->update([
            'subdomain' => $validated['subdomain'] ?: null,
            'custom_domain' => $validated['custom_domain'] ?: null,
        ]);

        return back()->with('success', "Domain tenant {$tenant->name} berhasil disimpan.");
    }

    public function approve(Tenant $tenant): RedirectResponse
    {
        $tenant->update(['status' => 'ACTIVE']);

        return back()->with('success', "Tenant {$tenant->name} sudah diaktifkan penuh.");
    }
}
