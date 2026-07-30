<?php

namespace App\Http\Controllers\MasterData;

use App\Exports\ItemOutletMappingExport;
use App\Exports\ItemPoTagsExport;
use App\Exports\ItemsExport;
use App\Exports\StockConfigExport;
use App\Exports\Templates\ItemImportTemplate;
use App\Exports\Templates\UnitConversionsImportTemplate;
use App\Exports\Templates\UnitsImportTemplate;
use App\Exports\UnitConversionsExport;
use App\Exports\UnitsExport;
use App\Http\Controllers\Controller;
use App\Imports\ItemPoTagsImport;
use App\Imports\ItemsImport;
use App\Imports\UnitConversionsImport;
use App\Imports\UnitsImport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
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

    // ── PO Tags (tujuan_po per item) ──────────────────────────────────────

    public function exportPoTags(Request $request): BinaryFileResponse
    {
        return Excel::download(
            new ItemPoTagsExport($this->tenantId($request)),
            'TujuanPO-Items.xlsx'
        );
    }

    public function previewPoTags(Request $request): JsonResponse
    {
        $import = new ItemPoTagsImport();
        Excel::import($import, $this->uploadedFile($request));

        return response()->json($import->preview($this->tenantId($request)));
    }

    public function applyPoTags(Request $request): JsonResponse
    {
        $request->validate([
            'changes'               => ['required', 'array', 'min:1'],
            'changes.*.sku'         => ['required', 'string', 'max:50'],
            'changes.*.has_change'  => ['required', 'boolean'],
            'changes.*.new_tags'    => ['present', 'array'],
            'changes.*.new_tags.*'  => ['string', Rule::in(['OCIA_ROASTERY', 'CENTRAL_KITCHEN'])],
        ]);

        $result = ItemPoTagsImport::applyChanges(
            $request->input('changes'),
            $this->tenantId($request)
        );

        return response()->json($result);
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
