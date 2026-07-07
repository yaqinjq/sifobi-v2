<?php

namespace App\Http\Controllers\Operations;

use App\Exports\Templates\OpenStockTemplate;
use App\Http\Controllers\Controller;
use App\Imports\OpenStockImport;
use App\Modules\Core\Models\Outlet;
use App\Services\OpenStockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;

class OpenStockImportController extends Controller
{
    public function showImport(Request $request): View
    {
        $tenantId = $this->tenantId($request);

        return view('operations.open-stocks.import', [
            'outlets' => Outlet::query()
                ->where('tenant_id', $tenantId)
                ->where('status', 'ACTIVE')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function template(Request $request)
    {
        return Excel::download(
            new OpenStockTemplate($this->tenantId($request)),
            'OpenStockTemplate.xlsx'
        );
    }

    public function import(Request $request, OpenStockService $openStockService): RedirectResponse
    {
        $tenantId = $this->tenantId($request);
        $validated = $request->validate([
            'outlet_id' => ['required', 'integer', Rule::exists('outlets', 'id')->where('tenant_id', $tenantId)],
            'file' => ['required', 'file', 'mimes:xlsx,xls', 'max:5120'],
        ]);

        $import = new OpenStockImport(
            $tenantId,
            (int) $validated['outlet_id'],
            (int) $request->user()->id,
            $openStockService
        );

        try {
            Excel::import($import, $validated['file']);
        } catch (\Throwable $throwable) {
            return back()
                ->withInput()
                ->with('error', 'Import gagal: '.$throwable->getMessage());
        }

        return back()->with('import_result', $import->summary());
    }

    private function tenantId(Request $request): int
    {
        $tenantId = $request->user()?->tenant_id;

        abort_unless($tenantId, 403, 'Tenant belum terpasang pada user.');

        return (int) $tenantId;
    }
}
