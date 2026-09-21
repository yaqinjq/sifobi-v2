<?php

namespace App\Http\Controllers\Settings;

use App\Exports\Templates\DepartmentCategoryMappingTemplate;
use App\Http\Controllers\Controller;
use App\Imports\DepartmentCategoryMappingImport;
use App\Modules\Core\Models\Department;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DepartmentCategoryMappingController extends Controller
{
    public function importForm(Request $request): View
    {
        $tenantId = $this->tenantId($request);

        $departments = Department::query()
            ->where('tenant_id', $tenantId)
            ->where('status', 'ACTIVE')
            ->with(['itemCategories' => fn ($q) => $q->whereNull('parent_id')->orderBy('name')])
            ->orderBy('name')
            ->get();

        return view('settings.department-category-mapping.import', [
            'departments' => $departments,
        ]);
    }

    public function import(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:10240'],
        ]);

        $tenantId = $this->tenantId($request);
        $import = new DepartmentCategoryMappingImport($tenantId);

        Excel::import($import, $validated['file']);

        $summary = $import->summary();

        return redirect()
            ->route('settings.department-category-mapping.import-form')
            ->with($summary['success'] ? 'success' : 'warning', "{$summary['processed']} baris berhasil diproses, {$summary['failed']} baris gagal.")
            ->with('importErrors', $summary['errors']);
    }

    public function importTemplate(): BinaryFileResponse
    {
        return Excel::download(
            new DepartmentCategoryMappingTemplate(),
            'Template-Mapping-Departemen-Kategori.xlsx'
        );
    }

    private function tenantId(Request $request): int
    {
        $tenantId = $request->user()?->tenant_id;

        abort_unless($tenantId, 403, 'Tenant belum terpasang pada user.');

        return (int) $tenantId;
    }
}
