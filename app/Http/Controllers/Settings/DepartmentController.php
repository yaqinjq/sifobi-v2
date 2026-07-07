<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Modules\Core\Models\Department;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DepartmentController extends Controller
{
    public function index(Request $request): View
    {
        $tenantId = $this->tenantId($request);

        return view('settings.departments.index', [
            'departments' => Department::query()
                ->where('tenant_id', $tenantId)
                ->where('status', 'ACTIVE')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $tenantId = $this->tenantId($request);
        $validated = $this->validated($request, $tenantId);

        DB::transaction(function () use ($tenantId, $validated): void {
            Department::query()->create([
                'tenant_id' => $tenantId,
                'code' => strtoupper($validated['code']),
                'name' => $validated['name'],
                'is_operational' => true,
                'status' => 'ACTIVE',
            ]);
        });

        return back()->with('success', 'Departemen berhasil ditambahkan.');
    }

    public function update(Request $request, Department $department): RedirectResponse
    {
        $tenantId = $this->tenantId($request);
        $this->authorizeTenant($department, $tenantId);

        $validated = $this->validated($request, $tenantId, $department->id);

        DB::transaction(fn () => $department->update([
            'code' => strtoupper($validated['code']),
            'name' => $validated['name'],
            'is_operational' => $request->boolean('is_operational', true),
            'status' => 'ACTIVE',
        ]));

        return back()->with('success', 'Departemen berhasil diperbarui.');
    }

    public function destroy(Request $request, Department $department): RedirectResponse
    {
        $tenantId = $this->tenantId($request);
        $this->authorizeTenant($department, $tenantId);

        DB::transaction(function () use ($department): void {
            if ($department->primaryItems()->exists() || $department->items()->exists()) {
                $department->update(['status' => 'INACTIVE']);

                return;
            }

            $department->delete();
        });

        return back()->with('success', 'Departemen berhasil dihapus dari pilihan aktif.');
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
                'max:32',
                Rule::unique('departments', 'code')
                    ->where('tenant_id', $tenantId)
                    ->ignore($ignoreId),
            ],
            'name' => ['required', 'string', 'max:255'],
            'is_operational' => ['nullable', 'boolean'],
        ]);
    }

    private function tenantId(Request $request): int
    {
        $tenantId = $request->user()?->tenant_id;

        abort_unless($tenantId, 403, 'Tenant belum terpasang pada user.');

        return (int) $tenantId;
    }

    private function authorizeTenant(Department $department, int $tenantId): void
    {
        abort_unless((int) $department->tenant_id === $tenantId, 404);
    }
}
