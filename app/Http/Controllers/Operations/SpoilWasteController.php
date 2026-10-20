<?php

namespace App\Http\Controllers\Operations;

use App\Http\Controllers\Concerns\EnforcesSuperAdminScope;
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
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SpoilWasteController extends Controller
{
    use EnforcesSuperAdminScope;
    use HasBulkAction;
    use HasPerPageSelector;

    public function __construct(private readonly SpoilWasteService $spoilWasteService)
    {
    }

    public function index(Request $request): View
    {
        $tenantId = $this->tenantId($request);
        [$perPage, $perPageOptions] = $this->perPageAndOptions($request, 20);

        $query = SpoilWaste::query()
            ->with(['outlet', 'department', 'item', 'unit', 'createdBy.roles'])
            ->where('tenant_id', $tenantId);
        $query = $this->applySpoilRecordScope($query, $request);

        $query = $query
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

        // Badge duplikat HARUS ikut scope yang sama dengan daftar di
        // bawahnya -- sebelumnya menghitung SELURUH tenant tanpa
        // memandang outlet/departemen viewer, jadi staf non-superadmin
        // melihat angka yang mencakup laporan di luar scope mereka.
        $duplicateCount = $this->applySpoilRecordScope(
            SpoilWaste::query()
                ->where('tenant_id', $tenantId)
                ->where('is_duplicate_photo', true)
                ->where('status', SpoilWaste::STATUS_PENDING),
            $request
        )->count();

        return view('operations.spoil-wastes.index', [
            'spoilWastes' => $query,
            'duplicateCount' => $duplicateCount,
            'perPage' => $perPage,
            'perPageOptions' => $perPageOptions,
            'pendingIds' => $pendingIds,
        ]);
    }

    public function create(Request $request): View
    {
        if (! $this->isSuperAdmin($request) && ! $this->hasValidSpoilScope($request)) {
            abort(403, 'Akun Anda belum ditetapkan ke outlet & departemen yang sah untuk mencatat spoil. Hubungi Super Admin.');
        }

        return view('operations.spoil-wastes.create', $this->formData($request));
    }

    public function store(Request $request): RedirectResponse
    {
        $tenantId = $this->tenantId($request);
        $user = $request->user();
        $isSuperAdmin = $this->isSuperAdmin($request);

        // Non-superadmin WAJIB punya outlet_id DAN department_id yang sah --
        // kalau tidak (mis. PIC_OUTLET/MANAGER_AREA yang desainnya lintas
        // departemen), akun itu TIDAK PUNYA scope yang valid untuk mencatat
        // spoil sama sekali di bawah kebijakan ini. Lihat laporan workflow
        // yang terdampak di commit message/laporan review.
        if (! $isSuperAdmin && ! $this->hasValidSpoilScope($request)) {
            abort(403, 'Akun Anda belum ditetapkan ke outlet & departemen yang sah untuk mencatat spoil. Hubungi Super Admin.');
        }

        $this->normalizeBatchInput($request);

        $validated = $this->validatedBatch($request, $tenantId, $isSuperAdmin);

        // outlet_id/department_id yang DIKIRIM CLIENT cuma dipakai untuk
        // SUPER_ADMIN -- untuk staf non-superadmin (sudah dipastikan
        // outlet+departemen-nya sah di atas), nilai dari akun mereka SELALU
        // menang, apa pun yang ada di form/request (jaga-jaga dari form yang
        // dimanipulasi, bukan cuma percaya UI dropdown yang sudah dibatasi).
        if (! $isSuperAdmin) {
            $validated['outlet_id'] = (int) $user->outlet_id;
            $validated['department_id'] = (int) $user->department_id;
        }

        $spoils = [];
        foreach ($validated['items'] as $index => $item) {
            // Format lama (1 field flat `photo`) dan format baru (baris
            // ke-N, `items[N][photo]`) harus sama-sama bisa diproses --
            // lihat normalizeBatchInput(). File TIDAK ikut ter-merge ke
            // `items[]` di sana (Request::merge() tidak menyentuh file
            // bag), jadi untuk baris pertama fallback ke field `photo`
            // kalau `items.0.photo` tidak ada.
            $photoFile = $request->hasFile("items.{$index}.photo")
                ? $request->file("items.{$index}.photo")
                : ($index === 0 ? $request->file('photo') : null);

            $spoils[] = $this->spoilWasteService->record([
                'tenant_id' => $tenantId,
                'outlet_id' => $validated['outlet_id'],
                'department_id' => $validated['department_id'],
                'recorded_date' => $validated['recorded_date'],
                'item_id' => $item['item_id'],
                'qty_whole' => $item['qty_whole'] ?? null,
                'qty_loose' => $item['qty_loose'] ?? null,
                'reason_category' => $item['reason_category'],
                'reason_detail' => $item['reason_detail'] ?? null,
                'photo_file' => $photoFile,
                'device_info' => $request->userAgent(),
                'ip_address' => $request->ip(),
            ], (int) $user->id);
        }

        if (count($spoils) === 1) {
            return redirect()
                ->route('operations.spoil-wastes.show', $spoils[0])
                ->with('success', 'Spoil berhasil dicatat, menunggu approval untuk pemotongan stok.');
        }

        return redirect()
            ->route('operations.spoil-wastes.index')
            ->with('success', count($spoils).' laporan spoil berhasil dicatat, menunggu approval untuk pemotongan stok.');
    }

    /**
     * Form lama cuma punya 1 item per submit (field flat: item_id,
     * qty_whole, dst). Form baru mendukung banyak item sekaligus lewat
     * array `items[]`. Supaya validatedBatch() cuma perlu menangani SATU
     * bentuk data, request lama dinormalisasi jadi `items[0]` di sini kalau
     * belum ada key `items` sama sekali -- tidak menyentuh file upload
     * (lihat catatan di store()).
     */
    private function normalizeBatchInput(Request $request): void
    {
        if ($request->has('items')) {
            return;
        }

        $request->merge([
            'items' => [
                [
                    'item_id' => $request->input('item_id'),
                    'qty_whole' => $request->input('qty_whole'),
                    'qty_loose' => $request->input('qty_loose'),
                    'reason_category' => $request->input('reason_category'),
                    'reason_detail' => $request->input('reason_detail'),
                ],
            ],
        ]);
    }

    public function show(Request $request, SpoilWaste $spoil): View
    {
        $spoil->load([
            'outlet',
            'department',
            'item.baseUnit',
            'item.inventoryUnit',
            'unit',
            'createdBy.roles',
            'approvedBy',
            'duplicateReference.createdBy',
            'mutation',
        ]);

        $this->assertSpoilInScope($request, $spoil);

        return view('operations.spoil-wastes.show', [
            'spoil' => $spoil,
        ]);
    }

    /**
     * Sajikan foto bukti spoil lewat endpoint ber-otentikasi + scope yang
     * SAMA PERSIS dengan show() -- bukan lagi URL publik lewat disk
     * 'public'. Foto BARU (sejak patch ini) tersimpan di disk 'local'
     * (storage/app/private, tidak ada symlink publik). Foto LAMA (sebelum
     * patch ini) masih di disk 'public' -- file-nya SENGAJA tidak
     * dipindah/dihapus di sini (lihat rencana migrasi di
     * docs/technical/), jadi tetap bisa dilayani lewat endpoint ini
     * (dengan scope check), TAPI url statis lamanya (/storage/...) juga
     * MASIH tetap bisa diakses langsung tanpa login sampai migrasi
     * dijalankan -- endpoint ini TIDAK menutup eksposur lama itu.
     *
     * Path foto SELALU diambil dari kolom $spoil->photo/photo_path pada
     * record yang sudah lolos assertSpoilInScope() (termasuk cek tenant
     * eksplisit di dalamnya) -- TIDAK PERNAH dari input client manapun.
     * Response dipaksa private/no-store supaya tidak tersimpan di cache
     * bersama/proxy maupun disk cache browser yang bisa diakses user lain
     * di perangkat/jaringan yang sama.
     */
    public function photo(Request $request, SpoilWaste $spoil): \Symfony\Component\HttpFoundation\Response
    {
        $this->assertSpoilInScope($request, $spoil);

        $path = $spoil->photo ?: $spoil->photo_path;
        abort_if(! $path, 404);

        $response = null;

        if (Storage::disk('local')->exists($path)) {
            $response = Storage::disk('local')->response($path);
        } elseif (Storage::disk('public')->exists($path)) {
            $response = Storage::disk('public')->response($path);
        }

        abort_if(! $response, 404);

        $response->headers->set('Cache-Control', 'private, no-store, max-age=0');

        return $response;
    }

    public function approve(Request $request, SpoilWaste $spoil): RedirectResponse
    {
        $this->assertSpoilInScope($request, $spoil);

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
        $requestedIds = array_map('intval', $request->input('ids', []));

        $scopedQuery = $this->applySpoilRecordScope(
            SpoilWaste::query()
                ->whereIn('id', $requestedIds)
                ->where('tenant_id', $tenantId)
                ->where('status', SpoilWaste::STATUS_PENDING),
            $request
        );

        $spoilWastes = $scopedQuery->get();

        // ID yang diminta client tapi TIDAK lolos scope (outlet/departemen
        // beda, atau user tidak punya scope valid sama sekali) HARUS
        // dilaporkan sebagai ditolak secara eksplisit -- bukan diam-diam
        // dilewati tanpa jejak seperti sebelumnya.
        $allowedIds = $spoilWastes->pluck('id')->all();
        $rejectedIds = array_values(array_diff($requestedIds, $allowedIds));

        $result = $this->runBulkAction(
            $spoilWastes,
            fn (SpoilWaste $spoil) => $this->spoilWasteService->approve($spoil, $userId, 'Bulk approve.'),
            fn (SpoilWaste $spoil) => "#{$spoil->id}"
        );

        foreach ($rejectedIds as $rejectedId) {
            $result['errors'][] = "#{$rejectedId}: di luar scope Anda (outlet/departemen berbeda), dilewati.";
        }
        $result['failed'] = count($result['errors']);

        return $this->bulkActionRedirect(
            'operations.spoil-wastes.index',
            $result,
            "{$result['processed']} spoil/waste berhasil di-approve."
        );
    }

    public function reject(Request $request, SpoilWaste $spoil): RedirectResponse
    {
        $this->assertSpoilInScope($request, $spoil);

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
        $search = $request->string('q')->trim()->toString();
        $isSuperAdmin = $this->isSuperAdmin($request);
        $userDepartmentId = $request->user()->department_id;

        if ($isSuperAdmin) {
            // SUPER_ADMIN boleh lihat saldo outlet mana pun, TAPI outlet
            // yang dipilih harus benar-benar divalidasi milik tenant yang
            // sama -- jangan percaya begitu saja outlet_id dari request
            // (bisa saja outlet_id outlet lain/tenant lain, cuma kebetulan
            // TenantScope yang sama-sama berlaku ke StockBalance akan
            // membuatnya kosong, tapi di sini kita tolak eksplisit bukan
            // cuma diam-diam kosong).
            $outletId = (int) $request->input('outlet_id', 0);

            if ($outletId > 0) {
                abort_unless(
                    Outlet::query()->where('tenant_id', $tenantId)->where('id', $outletId)->exists(),
                    422,
                    'Outlet tidak valid untuk tenant ini.'
                );
            }
        } else {
            // Non-superadmin: outlet_id SELALU dipaksa dari akun sendiri.
            // outlet_id yang dikirim lewat request DIABAIKAN TOTAL --
            // jangan sampai saldo outlet lain bisa "diintip" lewat
            // parameter ini cuma karena item-nya kebetulan boleh dicari.
            $outletId = (int) $request->user()->outlet_id;
        }

        $items = Item::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->where('track_stock', true)
            // Non-superadmin cuma boleh cari/catat spoil untuk item milik
            // departemen-nya (primary atau relasi departments) -- pola sama
            // dengan OpnameService::itemsForOpname() supaya daftar yang bisa
            // di-spoil konsisten dengan daftar yang dihitung di Opname.
            // Non-superadmin TANPA departemen yang sah (mis. PIC_OUTLET) --
            // fail closed, tidak ada item yang boleh dicari sama sekali.
            ->when(! $isSuperAdmin, function ($q) use ($userDepartmentId): void {
                if (! $userDepartmentId) {
                    $q->whereRaw('1 = 0');

                    return;
                }

                $q->where(fn ($dq) => $dq->where('primary_department_id', $userDepartmentId)
                    ->orWhereHas('departments', fn ($ddq) => $ddq->where('departments.id', $userDepartmentId)));
            })
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
        $isSuperAdmin = $this->isSuperAdmin($request);

        return [
            'outlets' => Outlet::query()
                ->where('tenant_id', $tenantId)
                ->where('status', 'ACTIVE')
                ->when(! $isSuperAdmin, fn ($q) => $q->where('id', $request->user()->outlet_id))
                ->orderBy('name')
                ->get(),
            'departments' => Department::query()
                ->where('tenant_id', $tenantId)
                ->where('status', 'ACTIVE')
                ->when(! $isSuperAdmin, fn ($q) => $q->where('id', $request->user()->department_id))
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
    private function validatedBatch(Request $request, int $tenantId, bool $isSuperAdmin): array
    {
        $userDepartmentId = $request->user()->department_id;

        $itemBelongsToDepartment = function (string $attribute, mixed $value, \Closure $fail) use ($tenantId, $userDepartmentId, $isSuperAdmin): void {
            if ($isSuperAdmin || ! $userDepartmentId) {
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
        };

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
            'recorded_date' => ['required', 'date'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_id' => [
                'required',
                'integer',
                Rule::exists('items', 'id')->where('tenant_id', $tenantId),
                // Pertahanan sisi server -- item_id tidak boleh cuma
                // dipercaya dari apa yang dikirim client (mis. request
                // yang dimanipulasi di luar autocomplete pencarian yang
                // sudah dibatasi di searchItems()). Non-superadmin cuma
                // boleh catat spoil untuk item departemen sendiri.
                // SUPER_ADMIN dikecualikan sepenuhnya (lintas departemen
                // memang wewenangnya).
                $itemBelongsToDepartment,
            ],
            'items.*.qty_whole' => ['nullable', Decimal::validationRule(6)],
            'items.*.qty_loose' => ['nullable', Decimal::validationRule(6)],
            'items.*.reason_category' => ['required', Rule::in([
                SpoilWaste::REASON_EXPIRED,
                SpoilWaste::REASON_RUSAK,
                SpoilWaste::REASON_KESALAHAN_PRODUKSI,
                SpoilWaste::REASON_TUMPAH,
                SpoilWaste::REASON_QUALITY_REJECT,
                SpoilWaste::REASON_LAINNYA,
            ])],
            'items.*.reason_detail' => ['nullable', 'string', 'max:2000'],
            // Dua rule foto di bawah ini menangani 2 bentuk request: form
            // lama kirim 1 file lewat field flat `photo` (lihat
            // normalizeBatchInput() -- file tidak ikut dinormalisasi ke
            // items[], jadi divalidasi terpisah di sini), form baru kirim
            // `items[N][photo]` per baris.
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'items.*.photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        foreach ($validated['items'] as $index => $item) {
            $qtyWhole = Decimal::toFixed($item['qty_whole'] ?? '0', 6);
            $qtyLoose = Decimal::toFixed($item['qty_loose'] ?? '0', 6);

            if (bccomp($qtyWhole, '0.000000', 6) <= 0 && bccomp($qtyLoose, '0.000000', 6) <= 0) {
                $itemNumber = $index + 1;
                throw ValidationException::withMessages([
                    "items.{$index}.qty_whole" => "Item #{$itemNumber}: isi minimal salah satu Jumlah Terbuang Utuh atau Ecer, harus lebih dari 0.",
                ]);
            }
        }

        return $validated;
    }

    public function importForm(Request $request): View
    {
        // Import bisa menulis data historis untuk outlet/departemen MANA
        // PUN yang disebut di file Excel-nya (bukan cuma scope uploader) --
        // ini bulk operation lintas-scope, jadi di bawah kebijakan "hanya
        // superadmin eksplisit lintas departemen", dibatasi SUPER_ADMIN saja
        // (sebelumnya PIC_OUTLET/MANAGER_AREA yang punya approve_spoil juga
        // bisa; lihat laporan workflow yang terdampak).
        abort_unless($this->isSuperAdmin($request), 403, 'Import histori Spoil & Waste cuma bisa dilakukan Super Admin.');

        return view('operations.spoil-wastes.import');
    }

    public function import(Request $request): RedirectResponse
    {
        abort_unless($this->isSuperAdmin($request), 403, 'Import histori Spoil & Waste cuma bisa dilakukan Super Admin.');

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
     * Terapkan scope SpoilWaste yang konsisten ke query list/bulk. SUPER_ADMIN
     * (role resmi, bukan permission approve_spoil) lewat tanpa batasan.
     * Non-superadmin WAJIB match outlet_id DAN department_id akun mereka
     * persis -- kalau salah satu kosong di akun, fail closed (tidak ada
     * baris yang cocok). Record lama dengan department_id NULL otomatis
     * TIDAK match (bukan di-exclude eksplisit, tapi karena perbandingan
     * `=` terhadap NULL di SQL tidak pernah true) -- jadi legacy null tetap
     * tidak terlihat staff, konsisten dengan kebijakan sebelumnya.
     *
     * Tenant dipaksa EKSPLISIT di sini juga (bukan cuma mengandalkan
     * caller yang kebetulan sudah menambahkan `where('tenant_id', ...)`
     * duluan) -- supaya method ini aman dipakai di mana pun tanpa diam-diam
     * membuka jalan lintas tenant kalau suatu saat ada caller baru yang
     * lupa menambahkan filter tenant_id-nya sendiri. Berlaku untuk SEMUA
     * user termasuk SUPER_ADMIN -- SUPER_ADMIN cuma dibebaskan dari batasan
     * outlet/departemen, TIDAK PERNAH dari batasan tenant.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<SpoilWaste>  $query
     * @return \Illuminate\Database\Eloquent\Builder<SpoilWaste>
     */
    private function applySpoilRecordScope($query, Request $request)
    {
        $query = $query->where('tenant_id', $request->user()->tenant_id);

        if ($this->isSuperAdmin($request)) {
            return $query;
        }

        if (! $this->hasValidSpoilScope($request)) {
            return $query->whereRaw('1 = 0');
        }

        $user = $request->user();

        return $query->where('outlet_id', $user->outlet_id)
            ->where('department_id', $user->department_id);
    }

    /**
     * Dipakai show()/photo()/approve()/reject() -- 403/404 kalau $spoil di
     * luar scope user (lihat applySpoilRecordScope() untuk aturan yang
     * sama pada query list/bulk).
     *
     * Cek tenant dilakukan EKSPLISIT di sini DULUAN, untuk SEMUA user
     * termasuk SUPER_ADMIN -- route-model-binding + TenantScope global
     * scope pada model SpoilWaste SEHARUSNYA sudah membuat record tenant
     * lain tidak ter-resolve sama sekali (404 duluan sebelum method ini
     * dipanggil), tapi itu mekanisme implisit yang bergantung pada global
     * scope tidak pernah di-bypass di tempat lain. Cek eksplisit di sini
     * adalah pertahanan lapis kedua yang tidak bergantung pada itu, dan
     * dibuktikan lewat test (lihat SpoilWasteDepartmentScopingFeatureTest)
     * -- bukan cuma diasumsikan aman dari perilaku Eloquent.
     */
    private function assertSpoilInScope(Request $request, SpoilWaste $spoil): void
    {
        $user = $request->user();

        abort_if((int) $spoil->tenant_id !== (int) $user->tenant_id, 404, 'Spoil tidak ditemukan.');

        if ($this->isSuperAdmin($request)) {
            return;
        }

        abort_unless(
            $this->hasValidSpoilScope($request),
            403,
            'Akun Anda belum ditetapkan ke outlet & departemen yang sah untuk mengakses spoil. Hubungi Super Admin.'
        );

        abort_if(
            (int) $spoil->outlet_id !== (int) $user->outlet_id
                || (int) $spoil->department_id !== (int) $user->department_id,
            403,
            'Anda tidak berwenang untuk spoil ini.'
        );
    }
}
