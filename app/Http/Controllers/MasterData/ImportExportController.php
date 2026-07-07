<?php

namespace App\Http\Controllers\MasterData;

use App\Exports\ItemOutletMappingExport;
use App\Exports\ItemsExport;
use App\Exports\StockConfigExport;
use App\Exports\Templates\ItemImportTemplate;
use App\Exports\Templates\UnitConversionsImportTemplate;
use App\Exports\Templates\UnitsImportTemplate;
use App\Exports\UnitConversionsExport;
use App\Exports\UnitsExport;
use App\Http\Controllers\Controller;
use App\Imports\ItemsImport;
use App\Imports\UnitConversionsImport;
use App\Imports\UnitsImport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ImportExportController extends Controller
{
    public function index(): View
    {
        return view('master-data.import-export.index');
    }

    public function exportItems(Request $request): BinaryFileResponse
    {
        return Excel::download(new ItemsExport($this->tenantId($request)), 'Items.xlsx');
    }

    public function exportUnits(Request $request): BinaryFileResponse
    {
        return Excel::download(new UnitsExport($this->tenantId($request)), 'Units.xlsx');
    }

    public function exportConversions(Request $request): BinaryFileResponse
    {
        return Excel::download(new UnitConversionsExport($this->tenantId($request)), 'UnitConversions.xlsx');
    }

    public function exportItemOutlets(Request $request): BinaryFileResponse
    {
        return Excel::download(new ItemOutletMappingExport($this->tenantId($request)), 'ItemOutletMapping.xlsx');
    }

    public function exportStockConfigs(Request $request): BinaryFileResponse
    {
        return Excel::download(new StockConfigExport($this->tenantId($request)), 'StockConfigs.xlsx');
    }

    public function templateItems(): BinaryFileResponse
    {
        return Excel::download(new ItemImportTemplate(), 'ItemImportTemplate.xlsx');
    }

    public function templateUnits(): BinaryFileResponse
    {
        return Excel::download(new UnitsImportTemplate(), 'UnitsTemplate.xlsx');
    }

    public function templateConversions(): BinaryFileResponse
    {
        return Excel::download(new UnitConversionsImportTemplate(), 'ConversionsTemplate.xlsx');
    }

    public function importItems(Request $request): JsonResponse
    {
        $import = new ItemsImport($this->tenantId($request));

        Excel::import($import, $this->uploadedFile($request));

        return response()->json($import->summary());
    }

    public function importUnits(Request $request): JsonResponse
    {
        $import = new UnitsImport($this->tenantId($request));

        Excel::import($import, $this->uploadedFile($request));

        return response()->json($import->summary());
    }

    public function importConversions(Request $request): JsonResponse
    {
        $import = new UnitConversionsImport($this->tenantId($request));

        Excel::import($import, $this->uploadedFile($request));

        return response()->json($import->summary());
    }

    private function tenantId(Request $request): int
    {
        $tenantId = $request->user()?->tenant_id;

        abort_unless($tenantId, 403, 'Tenant belum terpasang pada user.');

        return (int) $tenantId;
    }

    private function uploadedFile(Request $request): mixed
    {
        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:10240'],
        ]);

        return $validated['file'];
    }
}
