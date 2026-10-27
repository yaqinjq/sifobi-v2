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
use App\Modules\Operations\Models\OpnameSession;
use App\Services\InventoryCountService;
use App\Services\OpnameService;
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

    public function __construct(
        private readonly InventoryCountService $service,
        private readonly OpnameService $opnameService
    ) {
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
            // Opsi filter Lokasi & Kategori di Mode Tabel/List/Kartu --
            // dihitung dari baris dokumen ini sendiri (bukan semua lokasi
            // yang mungkin ada di sistem), supaya dropdown cuma berisi
            // pilihan yang relevan untuk dokumen yang sedang dibuka.
            'distinctLocations' => $document->lines->pluck('location')->unique()->sort()->values(),
            'distinctCategories' => $linesBySubcategory->keys()->sort()->values(),
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
        $isSuperAdmin = $this->isSuperAdmin($request);

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
            'hasValidScope' => (bool) $scope,
            'isSuperAdmin' => $isSuperAdmin,
            'outlets' => $isSuperAdmin ? Outlet::query()->where('tenant_id', $tenantId)->where('status', 'ACTIVE')->orderBy('name')->get() : collect(),
            'departments' => $isSuperAdmin ? Department::query()->where('tenant_id', $tenantId)->orderBy('name')->get() : collect(),
        ]);
    }

    /**
     * READ-ONLY: tampilkan data Opname BIASA (OpnameSession) yang sudah
     * diisi staf, di dalam bagian Inventory Simple -- murni supaya
     * kelihatan tanpa perlu isi ulang lewat Inventory Simple. TIDAK ADA
     * tulis/edit apa pun di sini, detail per sesi tetap pakai halaman
     * Opname biasa yang sudah ada (operations.opname.show) -- bukan
     * dibangun ulang. Sengaja terpisah dari history() di atas (yang query
     * InventoryCountDocument, bukan OpnameSession -- dua sumber data
     * yang beda sama sekali).
     */
    public function opnameHistory(Request $request): View
    {
        $tenantId = $this->tenantId($request);
        $scope = $this->resolveInventorySimpleScope($request, allowGeneralFinance: true);
        $canPickAnyScope = $this->canPickAnyOpnameRecapScope($request);

        $sessions = \App\Modules\Operations\Models\OpnameSession::query()
            ->with(['outlet', 'department'])
            ->withCount(['items', 'items as counted_items_count' => fn ($q) => $q->where('is_counted', true)])
            ->when($scope, function ($query) use ($scope): void {
                [$outletId, $departmentId] = $scope;
                $query->where('outlet_id', $outletId)->where('department_id', $departmentId);
            }, fn ($query) => $query->whereRaw('1 = 0'))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->upper()->toString()))
            ->latest('business_date')
            ->paginate(20)
            ->withQueryString();

        return view('operations.inventory-count.opname-history', [
            'sessions' => $sessions,
            'hasValidScope' => (bool) $scope,
            'canPickAnyScope' => $canPickAnyScope,
            'outlets' => $canPickAnyScope ? Outlet::query()->where('tenant_id', $tenantId)->where('status', 'ACTIVE')->orderBy('name')->get() : collect(),
            'departments' => $canPickAnyScope ? Department::query()->where('tenant_id', $tenantId)->orderBy('name')->get() : collect(),
        ]);
    }

    /**
     * READ-ONLY: versi "Rekap Hasil Hitung Mingguan" (grid item x 7 hari)
     * tapi sumber datanya OpnameSession/OpnameItem (Opname biasa), BUKAN
     * InventoryCountDocument/Line -- reuse view yang SAMA PERSIS dengan
     * Rekap Mingguan Inventory Simple (operations.inventory-count.recap)
     * supaya tampilannya identik seperti yang diminta user, tapi datanya
     * data Opname asli yang sudah ada. Sesi berstatus DRAFT TIDAK
     * dianggap hasil final (sama seperti aturan Rekap Mingguan Inventory
     * Simple) -- hari itu tampil "Belum dihitung".
     */
    /**
     * Batas lebar rentang tanggal bebas (date_from/date_to) di Rekap Opname
     * -- 1 bulan penuh dianggap cukup untuk kebutuhan dashboard/laporan,
     * sekaligus menjaga lebar tabel & query tetap wajar (bukan laporan
     * tahunan). Rentang yang diminta lebih panjang dipotong (bukan error)
     * supaya tetap tampil sesuatu, bukan halaman gagal total.
     */
    private const MAX_RECAP_RANGE_DAYS = 31;

    public function opnameRecap(Request $request): View
    {
        $tenantId = $this->tenantId($request);
        $scope = $this->resolveInventorySimpleScope($request, allowGeneralFinance: true);

        abort_if(! $scope, 403, 'Akun Anda belum ditetapkan ke outlet & departemen yang sah.');

        [$outletId, $departmentId] = $scope;

        [$rangeStart, $rangeEnd] = $this->resolveRecapRange($request);
        $weekStart = $rangeStart; // nama lama dipertahankan di view utk kompatibilitas dgn Rekap Inventory Simple non-opname.
        $dateRange = [];
        for ($cursor = $rangeStart->copy(); $cursor->lte($rangeEnd); $cursor->addDay()) {
            $dateRange[] = $cursor->copy();
        }

        // Opsional: ikut tampilkan sesi yang masih Draft (belum dikirim),
        // atas permintaan user supaya bisa mengecek data yang baru sedang
        // diisi -- SENGAJA ditandai berbeda di tampilan (lihat is_draft di
        // bawah), bukan disamakan dengan hasil final yang sudah Terkirim.
        $includeDraft = $request->boolean('include_draft');
        $statuses = [OpnameSession::STATUS_SUBMITTED, OpnameSession::STATUS_APPROVED, OpnameSession::STATUS_PROCESSED];
        if ($includeDraft) {
            $statuses[] = OpnameSession::STATUS_DRAFT;
        }

        $sessions = OpnameSession::query()
            ->where('tenant_id', $tenantId)
            ->where('outlet_id', $outletId)
            ->where('department_id', $departmentId)
            ->whereIn('status', $statuses)
            ->whereBetween('business_date', [$rangeStart->toDateString(), $rangeEnd->toDateString()])
            ->with(['items.item', 'items.unit'])
            ->get();

        $byDateItem = [];
        $statusByDate = [];
        foreach ($sessions as $session) {
            $dateKey = $session->business_date->toDateString();
            $statusByDate[$dateKey] = $session->status;
            foreach ($session->items as $opnameItem) {
                $byDateItem[$dateKey][$opnameItem->item_id] = $opnameItem;
            }
        }

        // Urut "Sesuai Form" (kategori utama -> sub kategori -> item), SAMA
        // PERSIS dengan tampilan Opname harian & Template Import Bulanan --
        // itemsForOpname() sendiri cuma orderBy('name') alfabetis, jadi
        // grouping kategori di bawah (groupBy('subcategory')) ikut baris
        // pertama yang ditemui kalau tidak diurut ulang di sini dulu.
        $items = $this->opnameService->itemsForOpname($tenantId, $outletId, OpnameSession::TYPE_DAILY, $departmentId)
            ->sortBy(fn ($item) => $item->formSortKey($departmentId));

        $rows = collect();
        foreach ($items as $item) {
            $days = [];
            foreach ($dateRange as $date) {
                $dateKey = $date->toDateString();
                $opnameItem = $byDateItem[$dateKey][$item->id] ?? null;

                $days[] = [
                    'date' => $dateKey,
                    'counted' => (bool) ($opnameItem?->is_counted),
                    'qty_whole' => $opnameItem?->is_counted ? $this->formatQty($opnameItem->physical_qty_base) : null,
                    'qty_loose' => null,
                    'whole_unit' => $opnameItem?->unit?->abbreviation ?? $opnameItem?->unit?->code,
                    'loose_unit' => null,
                    'config_valid' => true,
                    'is_draft' => ($statusByDate[$dateKey] ?? null) === OpnameSession::STATUS_DRAFT,
                ];
            }

            $rows->push([
                'item' => $item,
                'location' => 'GENERIC',
                'subcategory' => $item->categoryForDepartment($departmentId)?->name ?: 'Lainnya',
                'days' => $days,
            ]);
        }

        $canPickAnyScope = $this->canPickAnyOpnameRecapScope($request);

        return view('operations.inventory-count.recap', [
            'template' => null,
            'outlet' => Outlet::query()->find($outletId),
            'department' => Department::query()->find($departmentId),
            'weekStart' => $weekStart,
            'rangeStart' => $rangeStart,
            'rangeEnd' => $rangeEnd,
            'dateRange' => collect($dateRange),
            'rows' => $rows,
            'distinctCategories' => $rows->pluck('subcategory')->unique()->sort()->values(),
            'canPickAnyScope' => $canPickAnyScope,
            'outlets' => $canPickAnyScope ? Outlet::query()->where('tenant_id', $tenantId)->where('status', 'ACTIVE')->orderBy('name')->get() : collect(),
            'departments' => $canPickAnyScope ? Department::query()->where('tenant_id', $tenantId)->orderBy('name')->get() : collect(),
            'isOpnameRecap' => true,
            'includeDraft' => $includeDraft,
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
     * Resolusi rentang tanggal Rekap Opname: date_from/date_to (dari
     * date-range-picker) kalau ADA, kalau tidak fallback ke minggu
     * berjalan/dipilih lewat ?week= (perilaku lama, dipertahankan supaya
     * link lama -- mis. dari opname-history/index yang cuma kirim
     * outlet_id/department_id -- tetap jalan tanpa perlu diubah).
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    private function resolveRecapRange(Request $request): array
    {
        $dateFromParam = $request->date('date_from');
        $dateToParam = $request->date('date_to');

        if ($dateFromParam && $dateToParam) {
            $start = Carbon::instance($dateFromParam)->startOfDay();
            $end = Carbon::instance($dateToParam)->startOfDay();

            if ($end->lt($start)) {
                [$start, $end] = [$end, $start];
            }

            if ($start->diffInDays($end) > self::MAX_RECAP_RANGE_DAYS) {
                $end = $start->copy()->addDays(self::MAX_RECAP_RANGE_DAYS);
            }

            return [$start, $end];
        }

        $weekStartParam = $request->date('week');
        $start = ($weekStartParam ? Carbon::instance($weekStartParam) : now())
            ->copy()->startOfWeek(Carbon::MONDAY)->startOfDay();

        return [$start, $start->copy()->addDays(6)];
    }

    /**
     * Qty di-cast decimal:6 di model (presisi penuh buat perhitungan), tapi
     * tampilan di Rekap Mingguan cukup maks 2 angka di belakang koma supaya
     * tidak membingungkan (mis. "12.500000" -> "12.5", "10.000000" -> "10")
     * -- trailing zero & titik desimal yang tidak perlu dibuang.
     */
    private function formatQty(mixed $value): string
    {
        return rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.');
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
