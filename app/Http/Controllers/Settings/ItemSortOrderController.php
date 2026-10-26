<?php

namespace App\Http\Controllers\Settings;

use App\Exports\Templates\ItemSortOrderTemplate;
use App\Http\Controllers\Controller;
use App\Imports\ItemSortOrderImport;
use App\Modules\Core\Models\Department;
use App\Modules\Inventory\Models\Item;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class ItemSortOrderController extends Controller
{
    public function form(Request $request): View
    {
        $tenantId = $this->tenantId($request);

        return view('settings.item-sort-order.import', [
            'departments' => Department::query()
                ->where('tenant_id', $tenantId)
                ->where('status', 'ACTIVE')
                ->withCount('itemSortOrders')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function template(Request $request): BinaryFileResponse
    {
        $tenantId = $this->tenantId($request);
        $departmentId = (int) $this->validateDepartment($request, $tenantId)['department_id'];

        $department = Department::query()->where('tenant_id', $tenantId)->findOrFail($departmentId);

        $items = Item::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->whereHas('departments', fn ($q) => $q->where('departments.id', $departmentId))
            ->with(['category.parent', 'departmentCategories.category.parent', 'departmentSortOrders'])
            ->get();

        return Excel::download(
            new ItemSortOrderTemplate($items, $departmentId),
            "Template-Urutan-Item-{$department->name}.xlsx"
        );
    }

    public function import(Request $request): RedirectResponse
    {
        $tenantId = $this->tenantId($request);
        $validated = $this->validateDepartment($request, $tenantId);
        $validated['file'] = $request->validate(['file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:10240']])['file'];

        $departmentId = (int) $validated['department_id'];
        $path = $request->file('file')->store('item-sort-order-uploads', 'local');

        try {
            $import = new ItemSortOrderImport($tenantId, $departmentId);
            $import->import(Storage::disk('local')->path($path));
            $summary = $import->summary();
        } catch (Throwable $e) {
            Storage::disk('local')->delete($path);

            return redirect()
                ->route('settings.item-sort-order.import-form')
                ->with('error', 'Import gagal: '.$e->getMessage());
        }

        Storage::disk('local')->delete($path);

        return redirect()
            ->route('settings.item-sort-order.import-form')
            ->with($summary['success'] ? 'success' : 'warning', "{$summary['processed']} baris berhasil diproses, {$summary['failed']} baris gagal.")
            ->with('importErrors', $summary['errors']);
    }

    /**
     * @return array{department_id:int}
     */
    private function validateDepartment(Request $request, int $tenantId): array
    {
        return $request->validate([
            'department_id' => ['required', 'integer', Rule::exists('departments', 'id')->where('tenant_id', $tenantId)],
        ]);
    }

    private function tenantId(Request $request): int
    {
        $tenantId = $request->user()?->tenant_id;

        abort_unless($tenantId, 403, 'Tenant belum terpasang pada user.');

        return (int) $tenantId;
    }
}
