<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Modules\Core\Models\Brand;
use App\Modules\Core\Models\LegalEntity;
use App\Modules\Core\Models\Outlet;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class OutletController extends Controller
{
    public function index(Request $request): View
    {
        $tenantId = $this->tenantId($request);

        $query = Outlet::query()
            ->where('tenant_id', $tenantId)
            ->with(['brand', 'legalEntity']);

        if ($request->filled('brand_id')) {
            $query->where('brand_id', $request->integer('brand_id'));
        }

        return view('settings.outlets.index', [
            'outlets' => $query->orderBy('name')->get(),
            'brands' => $this->brands($request),
            'selectedBrandId' => $request->integer('brand_id') ?: null,
        ]);
    }

    public function create(Request $request): View
    {
        return view('settings.outlets.create', [
            'outlet' => new Outlet([
                'status' => 'ACTIVE',
                'outlet_type' => 'OUTLET',
                'timezone' => 'Asia/Jakarta',
            ]),
            'brands' => $this->brands($request),
            'legalEntities' => $this->legalEntities($request),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $tenantId = $this->tenantId($request);
        $data = $this->validated($request, $tenantId);

        DB::transaction(fn () => Outlet::withoutGlobalScopes()->create(array_merge($data, [
            'tenant_id' => $tenantId,
            'code' => strtoupper($data['code']),
            'outlet_type' => $data['outlet_type'] ?? 'OUTLET',
            'timezone' => $data['timezone'] ?? 'Asia/Jakarta',
        ])));

        return redirect()->route('settings.outlets.index')->with('success', 'Outlet berhasil ditambahkan.');
    }

    public function edit(Request $request, Outlet $outlet): View
    {
        $this->authorizeTenant($request, $outlet);

        return view('settings.outlets.edit', [
            'outlet' => $outlet,
            'brands' => $this->brands($request),
            'legalEntities' => $this->legalEntities($request),
        ]);
    }

    public function update(Request $request, Outlet $outlet): RedirectResponse
    {
        $this->authorizeTenant($request, $outlet);
        $tenantId = $this->tenantId($request);
        $data = $this->validated($request, $tenantId, $outlet->id);

        DB::transaction(fn () => $outlet->update(array_merge($data, [
            'code' => strtoupper($data['code']),
            'outlet_type' => $data['outlet_type'] ?? 'OUTLET',
            'timezone' => $data['timezone'] ?? 'Asia/Jakarta',
        ])));

        return redirect()->route('settings.outlets.index')->with('success', 'Outlet berhasil diperbarui.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, int $tenantId, ?int $ignoreId = null): array
    {
        return $request->validate([
            'brand_id' => ['required', 'integer', Rule::exists('brands', 'id')->where('tenant_id', $tenantId)],
            'legal_entity_id' => ['required', 'integer', Rule::exists('legal_entities', 'id')->where('tenant_id', $tenantId)],
            'code' => [
                'required',
                'string',
                'max:32',
                Rule::unique('outlets', 'code')
                    ->where('tenant_id', $tenantId)
                    ->ignore($ignoreId),
            ],
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:2000'],
            'contact_phone' => ['nullable', 'string', 'max:50'],
            'outlet_type' => ['nullable', 'string', 'max:32'],
            'timezone' => ['nullable', 'string', 'max:64'],
            'status' => ['required', Rule::in(['ACTIVE', 'INACTIVE'])],
        ]);
    }

    private function tenantId(Request $request): int
    {
        $tenantId = $request->user()?->tenant_id;

        abort_unless($tenantId, 403, 'Tenant belum terpasang pada user.');

        return (int) $tenantId;
    }

    private function authorizeTenant(Request $request, Outlet $outlet): void
    {
        abort_unless((int) $outlet->tenant_id === $this->tenantId($request), 404);
    }

    private function brands(Request $request)
    {
        return Brand::query()
            ->where('tenant_id', $this->tenantId($request))
            ->where('status', 'ACTIVE')
            ->orderBy('name')
            ->get();
    }

    private function legalEntities(Request $request)
    {
        return LegalEntity::query()
            ->where('tenant_id', $this->tenantId($request))
            ->where('status', 'ACTIVE')
            ->orderBy('name')
            ->get();
    }
}
