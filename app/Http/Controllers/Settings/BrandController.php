<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Modules\Core\Models\Brand;
use App\Modules\Core\Models\Group;
use App\Modules\Core\Models\Outlet;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class BrandController extends Controller
{
    public function index(Request $request): View
    {
        $tenantId = $this->tenantId($request);

        return view('settings.brands.index', [
            'brands' => Brand::query()
                ->where('tenant_id', $tenantId)
                ->withCount('outlets')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function create(Request $request): View
    {
        return view('settings.brands.create', [
            'brand' => new Brand(['status' => 'ACTIVE']),
            'groups' => $this->groups($request),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $tenantId = $this->tenantId($request);
        $data = $this->validated($request, $tenantId);

        DB::transaction(function () use ($request, $tenantId, $data): void {
            if ($request->hasFile('logo')) {
                $data['logo_path'] = $request->file('logo')->store("tenants/{$tenantId}/brands", 'public');
            }

            unset($data['logo']);

            Brand::withoutGlobalScopes()->create(array_merge($data, [
                'tenant_id' => $tenantId,
                'code' => strtoupper($data['code']),
            ]));
        });

        return redirect()->route('settings.brands.index')->with('success', 'Brand berhasil ditambahkan.');
    }

    public function edit(Request $request, Brand $brand): View
    {
        $this->authorizeTenant($request, $brand);

        return view('settings.brands.edit', [
            'brand' => $brand,
            'groups' => $this->groups($request),
        ]);
    }

    public function update(Request $request, Brand $brand): RedirectResponse
    {
        $this->authorizeTenant($request, $brand);
        $tenantId = $this->tenantId($request);
        $data = $this->validated($request, $tenantId, $brand->id);

        DB::transaction(function () use ($request, $brand, $tenantId, $data): void {
            if ($request->hasFile('logo')) {
                $data['logo_path'] = $request->file('logo')->store("tenants/{$tenantId}/brands", 'public');
            }

            unset($data['logo']);

            $brand->update(array_merge($data, [
                'code' => strtoupper($data['code']),
            ]));
        });

        return redirect()->route('settings.brands.index')->with('success', 'Brand berhasil diperbarui.');
    }

    public function destroy(Request $request, Brand $brand): RedirectResponse
    {
        $this->authorizeTenant($request, $brand);
        $tenantId = $this->tenantId($request);

        $outletCount = Outlet::query()
            ->where('tenant_id', $tenantId)
            ->where('brand_id', $brand->id)
            ->where('status', 'ACTIVE')
            ->count();

        if ($outletCount > 0) {
            return back()->with('error', "Brand {$brand->name} tidak bisa dihapus karena masih punya {$outletCount} outlet aktif.");
        }

        try {
            DB::transaction(fn () => $brand->delete());
        } catch (\Throwable) {
            return back()->with('error', "Brand {$brand->name} masih punya data terkait dan belum bisa dihapus.");
        }

        return redirect()
            ->route('settings.brands.index')
            ->with('success', "Brand {$brand->name} berhasil dihapus.");
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, int $tenantId, ?int $ignoreId = null): array
    {
        return $request->validate([
            'group_id' => ['nullable', 'integer', Rule::exists('groups', 'id')->where('tenant_id', $tenantId)],
            'code' => [
                'required',
                'string',
                'max:32',
                Rule::unique('brands', 'code')
                    ->where('tenant_id', $tenantId)
                    ->ignore($ignoreId),
            ],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'status' => ['required', Rule::in(['ACTIVE', 'INACTIVE'])],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:2048'],
        ]);
    }

    private function tenantId(Request $request): int
    {
        $tenantId = $request->user()?->tenant_id;

        abort_unless($tenantId, 403, 'Tenant belum terpasang pada user.');

        return (int) $tenantId;
    }

    private function authorizeTenant(Request $request, Brand $brand): void
    {
        abort_unless((int) $brand->tenant_id === $this->tenantId($request), 404);
    }

    private function groups(Request $request)
    {
        return Group::query()
            ->where('tenant_id', $this->tenantId($request))
            ->where('status', 'ACTIVE')
            ->orderBy('name')
            ->get();
    }
}
