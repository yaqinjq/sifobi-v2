<?php

namespace App\Http\Controllers\Operations;

use App\Exceptions\InsufficientStockException;
use App\Http\Controllers\Concerns\HasBulkAction;
use App\Http\Controllers\Concerns\HasPerPageSelector;
use App\Http\Controllers\Controller;
use App\Http\Requests\Operations\BulkApproveSpoilWasteRequest;
use App\Modules\Core\Models\Department;
use App\Modules\Core\Models\Outlet;
use App\Modules\Inventory\Models\Item;
use App\Modules\Operations\Models\SpoilWaste;
use App\Modules\Stock\Models\StockBalance;
use App\Modules\Stock\Models\StockMutation;
use App\Services\SpoilWasteService;
use App\Support\Decimal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SpoilWasteController extends Controller
{
    use HasBulkAction;
    use HasPerPageSelector;

    public function __construct(private readonly SpoilWasteService $spoilWasteService)
    {
    }

    public function index(Request $request): View
    {
        $tenantId = $this->tenantId($request);
        [$perPage, $perPageOptions] = $this->perPageAndOptions($request, 20);
        $userDepartmentId = $request->user()->department_id;
        $canSeeAllDepartments = (bool) $request->user()->can('approve_spoil');

        $query = SpoilWaste::query()
            ->with(['outlet', 'department', 'item', 'unit', 'createdBy'])
            ->where('tenant_id', $tenantId)
            ->when($request->user()->outlet_id, fn ($q) => $q->where('outlet_id', $request->user()->outlet_id))
            // User yang terikat 1 departemen cuma boleh lihat spoil
            // departemen-nya sendiri -- TIDAK termasuk record lama yang
            // department_id-nya NULL (beda dari pola Opname: di sini legacy
            // null sengaja tidak otomatis ikut terlihat staff). User tanpa
            // departemen tetap (PIC_OUTLET/MANAGER_AREA/SUPER_ADMIN) yang
            // juga tidak punya permission approve_spoil dianggap akun belum
            // di-assign dengan benar -- JANGAN diam-diam dianggap admin,
            // tampilkan kosong daripada bocor ke semua departemen.
            ->when($userDepartmentId, fn ($q) => $q->where('department_id', $userDepartmentId))
            ->when(! $userDepartmentId && ! $canSeeAllDepartments, fn ($q) => $q->whereNull('id'))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->upper()->toString()))
            ->when($request->input('range') === '7', fn ($q) => $q->whereDate('recorded_date', '>=', now()->subDays(7)->toDateString()))
            ->when($request->input('range') === '30', fn ($q) => $q->whereDate('recorded_date', '>=', now()->subDays(30)->toDateString()))
            ->when($request->input('range', 'today') === 'today', fn ($q) => $q->whereDate('recorded_date', now()->toDateString()))
            ->when($request->input('filter') === 'duplicate', fn ($q) => $q->where('is_duplicate_photo', true))
            ->when($request->filled('q'), function ($q) use ($request): void {
                $search = '%'.$request->string('q')->trim().'%';
                $q->whereHas('item', fn ($itemQuery) => $itemQuery
                    ->where('name', 'like', $search)
                    ->orWhere('canonical_sku', 'like', $search));
            })
            ->latest('recorded_at')
            ->paginate($perPage)
            ->withQueryString();

        $pendingIds = $query->getCollection()
            ->where('status', SpoilWaste::STATUS_PENDING)
            ->pluck('id')
            ->values();

        return view('operations.spoil-wastes.index', [
            'spoilWastes' => $query,
            'duplicateCount' => SpoilWaste::query()
                ->where('tenant_id', $tenantId)
                ->where('is_duplicate_photo', true)
                ->where('status', SpoilWaste::STATUS_PENDING)
                ->count(),
            'perPage' => $perPage,
            'perPageOptions' => $perPageOptions,
            'pendingIds' => $pendingIds,
        ]);
    }

    public function create(Request $request): View
    {
        return view('operations.spoil-wastes.create', $this->formData($request));
    }

    public function store(Request $request): RedirectResponse
    {
        $tenantId = $this->tenantId($request);
        $user = $request->user();
        $validated = $this->validated($request, $tenantId);

        // outlet_id/department_id yang DIKIRIM CLIENT cuma dipakai untuk
        // user yang memang tidak terikat (PIC_OUTLET/MANAGER_AREA/dst) --
        // untuk staf yang sudah terikat 1 outlet/departemen, nilai dari
        // akun mereka SELALU menang, apa pun yang ada di form/request
        // (jaga-jaga dari form yang dimanipulasi, bukan cuma percaya UI
        // dropdown yang sudah dibatasi).
        $validated['outlet_id'] = $user->outlet_id ?: (int) $validated['outlet_id'];
        $validated['department_id'] = $user->department_id ?: (int) $validated['department_id'];

        try {
            $spoil = $this->spoilWasteService->record(array_merge($validated, [
                'tenant_id' => $tenantId,
                'photo_file' => $request->file('photo'),
                'device_info' => $request->userAgent(),
                'ip_address' => $request->ip(),
            ]), (int) $user->id);
        } catch (InsufficientStockException $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('operations.spoil-wastes.show', $spoil)
            ->with('success', 'Spoil berhasil dicatat dan stok langsung berkurang.');
    }

    public function show(SpoilWaste $spoil): View
    {
        $spoil->load([
            'outlet',
            'department',
            'item.baseUnit',
            'item.inventoryUnit',
            'unit',
            'createdBy',
            'approvedBy',
            'duplicateReference.createdBy',
            'mutation',
        ]);

        $user = auth()->user();
        $userOutletId = $user->outlet_id;
        abort_if($userOutletId && (int) $spoil->outlet_id !== (int) $userOutletId, 403);

        $userDepartmentId = $user->department_id;
        if ($userDepartmentId) {
            // Sengaja TIDAK mengecualikan department_id NULL (legacy) --
            // staf departemen tertentu cuma boleh buka spoil departemen
            // sendiri, titik.
            abort_if((int) $spoil->department_id !== (int) $userDepartmentId, 403);
        } elseif (! $user->can('approve_spoil')) {
            abort(403);
        }

        return view('operations.spoil-wastes.show', [
            'spoil' => $spoil,
        ]);
    }

    public function approve(Request $request, SpoilWaste $spoil): RedirectResponse
    {
        $this->authorizeSpoilOwnership($request, $spoil);

        $validated = $request->validate([
            'approval_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $updated = $this->spoilWasteService->approve(
            $spoil,
            (int) $request->user()->id,
            $validated['approval_notes'] ?? ''
        );

        return redirect()
            ->route('operations.spoil-wastes.show', $updated)
            ->with('success', 'Spoil berhasil di-approve.');
    }

    public function bulkApprove(BulkApproveSpoilWasteRequest $request): RedirectResponse
    {
        $tenantId = $this->tenantId($request);
        $userId = (int) $request->user()->id;

        $spoilWastes = SpoilWaste::query()
            ->whereIn('id', $request->input('ids', []))
            ->where('tenant_id', $tenantId)
            ->when($request->user()->outlet_id, fn ($q) => $q->where('outlet_id', $request->user()->outlet_id))
            ->where('status', SpoilWaste::STATUS_PENDING)
            ->get();

        $result = $this->runBulkAction(
            $spoilWastes,
            fn (SpoilWaste $spoil) => $this->spoilWasteService->approve($spoil, $userId, 'Bulk approve.'),
            fn (SpoilWaste $spoil) => "#{$spoil->id}"
        );

        return $this->bulkActionRedirect(
            'operations.spoil-wastes.index',
            $result,
            "{$result['processed']} spoil/waste berhasil di-approve."
        );
    }

    public function reject(Request $request, SpoilWaste $spoil): RedirectResponse
    {
        $this->authorizeSpoilOwnership($request, $spoil);

        $validated = $request->validate([
            'approval_notes' => ['required', 'string', 'max:1000'],
        ]);

        $updated = $this->spoilWasteService->reject(
            $spoil,
            (int) $request->user()->id,
            $validated['approval_notes']
        );

        return redirect()
            ->route('operations.spoil-wastes.show', $updated)
            ->with('warning', 'Spoil ditolak dan stok dikembalikan lewat VOID_REVERSAL.');
    }

    public function searchItems(Request $request): JsonResponse
    {
        $tenantId = $this->tenantId($request);
        $outletId = (int) ($request->input('outlet_id') ?: $request->user()->outlet_id);
        $search = $request->string('q')->trim()->toString();
        $userDepartmentId = $request->user()->department_id;

        $items = Item::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->where('track_stock', true)
            // Staf yang terikat 1 departemen cuma boleh cari/catat spoil
            // untuk item milik departemen-nya (primary atau relasi
            // departments) -- pola sama dengan OpnameService::itemsForOpname()
            // supaya daftar yang bisa di-spoil konsisten dengan daftar yang
            // dihitung di Opname.
            ->when($userDepartmentId, fn ($q) => $q->where(
                fn ($dq) => $dq->where('primary_department_id', $userDepartmentId)
                    ->orWhereHas('departments', fn ($ddq) => $ddq->where('departments.id', $userDepartmentId))
            ))
            ->with(['baseUnit', 'inventoryUnit'])
            ->when($search !== '', fn ($query) => $query->where(fn ($inner) => $inner
                ->where('name', 'like', "%{$search}%")
                ->orWhere('canonical_sku', 'like', "%{$search}%")))
            ->orderBy('name')
            ->limit(20)
            ->get();

        return response()->json($items->map(function (Item $item) use ($tenantId, $outletId): array {
            $balance = StockBalance::query()
                ->where('tenant_id', $tenantId)
                ->where('outlet_id', $outletId)
                ->where('item_id', $item->id)
                ->where('stock_target', StockMutation::TARGET_OUTLET_DAILY)
                ->first();

            return [
                'id' => $item->id,
                'name' => $item->name,
                'sku' => $item->canonical_sku,
                'base_unit_id' => $item->base_unit_id,
                'base_unit' => $item->baseUnit?->abbreviation ?? $item->baseUnit?->code ?? 'base',
                'inventory_unit_id' => $item->inventory_unit_id ?: $item->base_unit_id,
                'inventory_unit' => $item->inventoryUnit?->abbreviation ?? $item->inventoryUnit?->code ?? $item->baseUnit?->abbreviation ?? 'unit',
                'inventory_ratio' => (float) ($item->inventory_ratio ?: 1),
                'qty_on_hand' => (float) ($balance?->qty_on_hand ?? 0),
            ];
        }));
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(Request $request): array
    {
        $tenantId = $this->tenantId($request);

        return [
            'outlets' => Outlet::query()
                ->where('tenant_id', $tenantId)
                ->where('status', 'ACTIVE')
                ->when($request->user()->outlet_id, fn ($q) => $q->where('id', $request->user()->outlet_id))
                ->orderBy('name')
                ->get(),
            'departments' => Department::query()
                ->where('tenant_id', $tenantId)
                ->where('status', 'ACTIVE')
                ->when($request->user()->department_id, fn ($q) => $q->where('id', $request->user()->department_id))
                ->orderBy('name')
                ->get(),
            'reasonOptions' => [
                SpoilWaste::REASON_EXPIRED => 'Kadaluarsa',
                SpoilWaste::REASON_RUSAK => 'Rusak/Cacat',
                SpoilWaste::REASON_KESALAHAN_PRODUKSI => 'Kesalahan Produksi',
                SpoilWaste::REASON_TUMPAH => 'Tumpah',
                SpoilWaste::REASON_QUALITY_REJECT => 'Reject Kualitas',
                SpoilWaste::REASON_LAINNYA => 'Lainnya',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, int $tenantId): array
    {
        $userDepartmentId = $request->user()->department_id;

        $validated = $request->validate([
            'outlet_id' => [
                'required',
                'integer',
                Rule::exists('outlets', 'id')->where('tenant_id', $tenantId),
            ],
            'department_id' => [
                'required',
                'integer',
                Rule::exists('departments', 'id')->where('tenant_id', $tenantId),
            ],
            'item_id' => [
                'required',
                'integer',
                Rule::exists('items', 'id')->where('tenant_id', $tenantId),
                // Pertahanan sisi server -- item_id tidak boleh cuma
                // dipercaya dari apa yang dikirim client (mis. request
                // yang dimanipulasi di luar autocomplete pencarian yang
                // sudah dibatasi di searchItems()). Staf yang terikat 1
                // departemen cuma boleh catat spoil untuk item departemen
                // sendiri.
                function (string $attribute, mixed $value, \Closure $fail) use ($tenantId, $userDepartmentId): void {
                    if (! $userDepartmentId) {
                        return;
                    }

                    $belongsToDepartment = Item::withoutGlobalScopes()
                        ->where('tenant_id', $tenantId)
                        ->where('id', $value)
                        ->where(fn ($q) => $q->where('primary_department_id', $userDepartmentId)
                            ->orWhereHas('departments', fn ($dq) => $dq->where('departments.id', $userDepartmentId)))
                        ->exists();

                    if (! $belongsToDepartment) {
                        $fail('Item ini bukan milik departemen Anda.');
                    }
                },
            ],
            'qty_whole' => ['nullable', Decimal::validationRule(6)],
            'qty_loose' => ['nullable', Decimal::validationRule(6)],
            'recorded_date' => ['required', 'date'],
            'reason_category' => ['required', Rule::in([
                SpoilWaste::REASON_EXPIRED,
                SpoilWaste::REASON_RUSAK,
                SpoilWaste::REASON_KESALAHAN_PRODUKSI,
                SpoilWaste::REASON_TUMPAH,
                SpoilWaste::REASON_QUALITY_REJECT,
                SpoilWaste::REASON_LAINNYA,
            ])],
            'reason_detail' => ['nullable', 'string', 'max:2000'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $qtyWhole = Decimal::toFixed($validated['qty_whole'] ?? '0', 6);
        $qtyLoose = Decimal::toFixed($validated['qty_loose'] ?? '0', 6);

        if (bccomp($qtyWhole, '0.000000', 6) <= 0 && bccomp($qtyLoose, '0.000000', 6) <= 0) {
            throw ValidationException::withMessages([
                'qty_whole' => 'Isi minimal salah satu: Jumlah Terbuang Utuh atau Ecer, harus lebih dari 0.',
            ]);
        }

        return $validated;
    }

    public function importForm(Request $request): View
    {
        return view('operations.spoil-wastes.import');
    }

    public function import(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:10240'],
        ]);

        $tenantId = $this->tenantId($request);
        $import = new \App\Imports\SpoilWasteImport($tenantId, (int) $request->user()->id, $this->spoilWasteService);

        \Maatwebsite\Excel\Facades\Excel::import($import, $validated['file']);

        $summary = $import->summary();

        return redirect()
            ->route('operations.spoil-wastes.import-form')
            ->with($summary['success'] ? 'success' : 'warning', "{$summary['inserted']} baris berhasil diimpor & di-approve, {$summary['failed']} baris gagal.")
            ->with('importErrors', $summary['errors']);
    }

    public function importTemplate(): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\Templates\SpoilWasteImportTemplate(),
            'Template-Import-SpoilWaste.xlsx'
        );
    }

    private function tenantId(Request $request): int
    {
        $tenantId = $request->user()?->tenant_id;

        abort_unless($tenantId, 403, 'Tenant belum terpasang pada user.');

        return (int) $tenantId;
    }

    /**
     * Dipakai approve()/reject() -- keduanya sudah digerbang permission
     * approve_spoil di route (cuma dipegang SUPER_ADMIN/ADMIN, MANAGER_AREA,
     * PIC_OUTLET -- tidak pernah oleh STAFF_* yang terikat departemen),
     * jadi cukup cek OUTLET di sini (PIC_OUTLET tidak boleh approve spoil
     * outlet lain). Approve_spoil secara desain memang lintas departemen
     * dalam 1 outlet (itu tugasnya), jadi sengaja TIDAK ada pengecekan
     * departemen tambahan di sini.
     */
    private function authorizeSpoilOwnership(Request $request, SpoilWaste $spoil): void
    {
        $userOutletId = $request->user()->outlet_id;
        abort_if($userOutletId && (int) $spoil->outlet_id !== (int) $userOutletId, 403, 'Anda tidak berwenang untuk spoil outlet lain.');
    }
}
