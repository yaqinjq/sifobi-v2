<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TenantController extends Controller
{
    public function index(): View
    {
        $tenants = Tenant::query()->orderBy('name')->get();

        return view('admin.tenants.index', compact('tenants'));
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
}
