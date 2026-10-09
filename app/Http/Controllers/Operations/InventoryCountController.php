<?php

namespace App\Http\Controllers\Operations;

use App\Exceptions\InventoryCountConflictException;
use App\Http\Controllers\Concerns\EnforcesInventorySimpleScope;
use App\Http\Controllers\Controller;
use App\Modules\Core\Models\Department;
use App\Modules\Core\Models\Outlet;
use App\Modules\Operations\Models\InventoryCountDocument;
use App\Modules\Operations\Models\InventoryCountLine;
use App\Modules\Operations\Models\InventoryCountTemplate;
use App\Services\InventoryCountService;
use App\Support\Decimal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class InventoryCountController extends Controller
{
    use EnforcesInventorySimpleScope;

    public function __construct(private readonly InventoryCountService $service)
    {
    }

    /**
     * Beranda: "Isi Inventory" (pilih template) + ringkasan "Riwayat".
     */
    public function index(Request $request): View
    {
        $tenantId = $this->tenantId($request);
        $scope = $this->resolveInventorySimpleScope($request);

        $templates = collect();
        $outlet = null;
        $department = null;

        if ($scope) {
            [$outletId, $departmentId] = $scope;
            $outlet = Outlet::query()->where('tenant_id', $tenantId)->find($outletId);
            $department = Department::query()->where('tenant_id', $tenantId)->find($departmentId);
            $templates = $this->service->availableTemplates($tenantId, $departmentId);
        }

        $recentDocuments = InventoryCountDocument::query()
            ->with(['template', 'outlet', 'department'])
            ->when($scope, function ($query) use ($scope): void {
                [$outletId, $departmentId] = $scope;
                $query->where('outlet_id', $outletId)->where('department_id', $departmentId);
            }, fn ($query) => $query->whereRaw('1 = 0'))
            ->latest('business_date')
            ->limit(10)
            ->get();

        return view('operations.inventory-count.index', [
            'hasValidScope' => (bool) $scope,
            'isSuperAdmin' => $this->isSuperAdmin($request),
            'outlet' => $outlet,
            'department' => $department,
            'templates' => $templates,
            'recentDocuments' => $recentDocuments,
            'outlets' => $this->isSuperAdmin($request) ? Outlet::query()->where('tenant_id', $tenantId)->where('status', 'ACTIVE')->orderBy('name')->get() : collect(),
            'departments' => $this->isSuperAdmin($request) ? Department::query()->where('tenant_id', $tenantId)->orderBy('name')->get() : collect(),
        ]);
    }

    public function startOrResume(Request $request): RedirectResponse
    {
        $tenantId = $this->tenantId($request);
        $scope = $this->resolveInventorySimpleScope($request);

        abort_if(! $scope, 403, 'Akun Anda belum ditetapkan ke outlet & departemen yang sah untuk Inventory Simple. Hubungi Super Admin.');

        [$outletId, $departmentId] = $scope;

        $validated = $request->validate([
            'template_id' => ['required', 'integer'],
            'business_date' => ['nullable', 'date'],
        ]);

        $template = InventoryCountTemplate::query()
            ->where('tenant_id', $tenantId)
            ->where('status', InventoryCountTemplate::STATUS_ACTIVE)
            ->where(fn ($q) => $q->where('department_id', $departmentId)->orWhere('is_generic', true))
            ->find($validated['template_id']);

        abort_unless($template, 404, 'Template tidak ditemukan atau bukan milik departemen Anda.');

        $businessDate = isset($validated['business_date'])
            ? Carbon::parse($validated['business_date'])
            : now();

        $document = $this->service->createOrResumeDraft(
            $tenantId,
            $outletId,
            $departmentId,
            $template,
            $businessDate,
            (int) $request->user()->id
        );

        return redirect()->route('operations.inventory-count.show', $document);
    }

    public function show(Request $request, InventoryCountDocument $document): View
    {
        $this->assertDocumentInScope($request, $document);

        $document->load(['template', 'outlet', 'department', 'lines.item.baseUnit', 'lines.wholeUnit', 'lines.looseUnit', 'revisions.performedBy']);

        $linesBySubcategory = $document->lines->groupBy(fn ($line) => $line->subcategory_label ?: 'Lainnya');

        return view('operations.inventory-count.show', [
            'document' => $document,
            'linesBySubcategory' => $linesBySubcategory,
            'countedCount' => $document->lines->where('is_counted', true)->count(),
            'totalCount' => $document->lines->count(),
            'canRequestRevision' => $request->user()->can('approve_opname'),
        ]);
    }

    public function autosaveLine(Request $request, InventoryCountLine $line): JsonResponse
    {
        $this->assertLineInScope($request, $line);

        // Proyek ini mengaktifkan shouldRenderJsonWhen(fn ($r) => $r->is('api/*'))
        // di bootstrap/app.php -- berarti ValidationException dari
        // $request->validate() TIDAK otomatis dirender JSON untuk route
        // non-api/* seperti endpoint ini (malah redirect balik dengan
        // errors di session, sesuai desain app untuk halaman biasa).
        // Endpoint ini dipanggil lewat fetch() dari JS yang mengharapkan
        // JSON apa pun hasilnya -- jadi exception-nya ditangkap eksplisit
        // di sini dan direspon manual sebagai JSON 422, sama polanya
        // dengan InventoryCountConflictException di bawah.
        try {
            // Decimal::validationRule() menerima koma ATAU titik sebagai
            // desimal (tapi menolak kalau KEDUANYA muncul -- ambigu sebagai
            // pemisah ribuan) -- sama persis pola yang sudah dipakai
            // Spoil/OpenStock/Opname, supaya staf yang terbiasa input koma
            // (lazim di Indonesia) tidak ditolak validasi "numeric" standar
            // Laravel (yang cuma menerima titik).
            $validated = $request->validate([
                'qty_whole' => ['nullable', Decimal::validationRule(6)],
                'qty_loose' => ['nullable', Decimal::validationRule(6)],
                'notes' => ['nullable', 'string', 'max:500'],
                'clear' => ['nullable', 'boolean'],
                'expected_version' => ['required', 'integer'],
            ]);
        } catch (ValidationException $exception) {
            return response()->json(['message' => 'Validasi gagal.', 'errors' => $exception->errors()], 422);
        }

        try {
            $updated = $this->service->autosaveLine(
                $line,
                $validated,
                (int) $request->user()->id,
                (int) $validated['expected_version']
            );
        } catch (InventoryCountConflictException $exception) {
            return response()->json(['message' => $exception->getMessage()], 409);
        }

        return response()->json([
            'line' => [
                'id' => $updated->id,
                'qty_whole' => $updated->qty_whole,
                'qty_loose' => $updated->qty_loose,
                'is_counted' => $updated->is_counted,
                'qty_base' => $updated->qty_base,
            ],
            'document_version' => $updated->document->version,
        ]);
    }

    public function markZero(Request $request, InventoryCountLine $line): JsonResponse
    {
        $this->assertLineInScope($request, $line);

        try {
            $validated = $request->validate([
                'expected_version' => ['required', 'integer'],
            ]);
        } catch (ValidationException $exception) {
            return response()->json(['message' => 'Validasi gagal.', 'errors' => $exception->errors()], 422);
        }

        try {
            $updated = $this->service->markZero($line, (int) $request->user()->id, (int) $validated['expected_version']);
        } catch (InventoryCountConflictException $exception) {
            return response()->json(['message' => $exception->getMessage()], 409);
        }

        return response()->json([
            'line' => [
                'id' => $updated->id,
                'qty_whole' => $updated->qty_whole,
                'qty_loose' => $updated->qty_loose,
                'is_counted' => $updated->is_counted,
            ],
            'document_version' => $updated->document->version,
        ]);
    }

    public function submit(Request $request, InventoryCountDocument $document): RedirectResponse
    {
        $this->assertDocumentInScope($request, $document);

        $validated = $request->validate([
            'expected_version' => ['required', 'integer'],
        ]);

        try {
            $this->service->submit($document, (int) $request->user()->id, (int) $validated['expected_version']);
        } catch (InventoryCountConflictException $exception) {
            return back()->with('error', $exception->getMessage());
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors());
        }

        return redirect()
            ->route('operations.inventory-count.show', $document)
            ->with('success', 'Hasil hitung terkirim. Ini belum mengubah saldo stok sistem.');
    }

    public function requestRevision(Request $request, InventoryCountDocument $document): RedirectResponse
    {
        $this->assertDocumentInScope($request, $document);

        abort_unless($request->user()->can('approve_opname'), 403, 'Anda tidak berwenang meminta revisi.');

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        try {
            $this->service->requestRevision($document, (int) $request->user()->id, $validated['reason']);
        } catch (InventoryCountConflictException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('operations.inventory-count.show', $document)
            ->with('warning', 'Dokumen dibuka kembali untuk revisi.');
    }

    public function history(Request $request): View
    {
        $tenantId = $this->tenantId($request);
        $scope = $this->resolveInventorySimpleScope($request);

        $documents = InventoryCountDocument::query()
            ->with(['template', 'outlet', 'department'])
            ->when($scope, function ($query) use ($scope): void {
                [$outletId, $departmentId] = $scope;
                $query->where('outlet_id', $outletId)->where('department_id', $departmentId);
            }, fn ($query) => $query->whereRaw('1 = 0'))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->upper()->toString()))
            ->when($request->filled('template_id'), fn ($q) => $q->where('template_id', $request->integer('template_id')))
            ->latest('business_date')
            ->paginate(20)
            ->withQueryString();

        return view('operations.inventory-count.history', [
            'documents' => $documents,
            'templates' => $scope ? $this->service->availableTemplates($tenantId, $scope[1]) : collect(),
        ]);
    }

    public function export(Request $request): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $tenantId = $this->tenantId($request);
        $scope = $this->resolveInventorySimpleScope($request);

        abort_if(! $scope, 403, 'Akun Anda belum ditetapkan ke outlet & departemen yang sah.');

        [$outletId, $departmentId] = $scope;

        $filters = $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'template_id' => ['nullable', 'integer'],
            'status' => ['nullable', 'string'],
        ]);

        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\Reports\InventoryCountExport($tenantId, $outletId, $departmentId, $filters),
            'InventorySimple-'.now()->format('Ymd-His').'.xlsx'
        );
    }

    private function tenantId(Request $request): int
    {
        $tenantId = $request->user()?->tenant_id;
        abort_unless($tenantId, 403, 'Tenant belum terpasang pada user.');

        return (int) $tenantId;
    }

    /**
     * Cek tenant EKSPLISIT duluan (untuk semua user termasuk SUPER_ADMIN),
     * baru cek scope outlet/departemen untuk non-superadmin.
     */
    private function assertDocumentInScope(Request $request, InventoryCountDocument $document): void
    {
        $user = $request->user();

        abort_if((int) $document->tenant_id !== (int) $user->tenant_id, 404, 'Dokumen tidak ditemukan.');

        if ($this->isSuperAdmin($request)) {
            return;
        }

        $scope = $this->resolveInventorySimpleScope($request);

        abort_unless($scope, 403, 'Akun Anda belum ditetapkan ke outlet & departemen yang sah.');

        [$outletId, $departmentId] = $scope;

        abort_if(
            (int) $document->outlet_id !== $outletId || (int) $document->department_id !== $departmentId,
            403,
            'Anda tidak berwenang untuk dokumen ini.'
        );
    }

    private function assertLineInScope(Request $request, InventoryCountLine $line): void
    {
        $document = InventoryCountDocument::query()->findOrFail($line->document_id);
        $this->assertDocumentInScope($request, $document);
    }
}
