<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Modules\Receiving\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SupplierController extends Controller
{
    public function index(Request $request): View
    {
        $tenantId = $this->tenantId($request);

        return view('settings.suppliers.index', [
            'suppliers' => Supplier::query()
                ->where('tenant_id', $tenantId)
                ->orderByDesc('is_active')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $tenantId = $this->tenantId($request);
        $validated = $this->validated($request, $tenantId);

        DB::transaction(function () use ($tenantId, $validated): void {
            Supplier::query()->create([
                'tenant_id' => $tenantId,
                'code' => strtoupper($validated['code']),
                'name' => $validated['name'],
                'contact_name' => $validated['contact_name'] ?? null,
                'phone' => $validated['phone'] ?? null,
                'email' => $validated['email'] ?? null,
                'address' => $validated['address'] ?? null,
                'is_active' => true,
            ]);
        });

        return back()->with('success', 'Supplier berhasil ditambahkan.');
    }

    public function update(Request $request, Supplier $supplier): RedirectResponse
    {
        $tenantId = $this->tenantId($request);
        $this->authorizeTenant($supplier, $tenantId);
        $validated = $this->validated($request, $tenantId, $supplier->id);

        DB::transaction(fn () => $supplier->update([
            'code' => strtoupper($validated['code']),
            'name' => $validated['name'],
            'contact_name' => $validated['contact_name'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'email' => $validated['email'] ?? null,
            'address' => $validated['address'] ?? null,
            'is_active' => $request->boolean('is_active', true),
        ]));

        return back()->with('success', 'Supplier berhasil diperbarui.');
    }

    public function destroy(Request $request, Supplier $supplier): RedirectResponse
    {
        $tenantId = $this->tenantId($request);
        $this->authorizeTenant($supplier, $tenantId);

        DB::transaction(function () use ($supplier): void {
            if ($supplier->goodsReceipts()->exists()) {
                $supplier->update(['is_active' => false]);

                return;
            }

            $supplier->delete();
        });

        return back()->with('success', 'Supplier berhasil dihapus dari pilihan aktif.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, int $tenantId, ?int $ignoreId = null): array
    {
        return $request->validate([
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('suppliers', 'code')->where('tenant_id', $tenantId)->ignore($ignoreId),
            ],
            'name' => ['required', 'string', 'max:255'],
            'contact_name' => ['nullable', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:150'],
            'address' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['nullable', 'boolean'],
        ]);
    }

    private function tenantId(Request $request): int
    {
        $tenantId = $request->user()?->tenant_id;

        abort_unless($tenantId, 403, 'Tenant belum terpasang pada user.');

        return (int) $tenantId;
    }

    private function authorizeTenant(Supplier $supplier, int $tenantId): void
    {
        abort_unless((int) $supplier->tenant_id === $tenantId, 404);
    }
}
