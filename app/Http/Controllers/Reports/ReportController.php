<?php

namespace App\Http\Controllers\Reports;

use App\Exports\Reports\KartuStokDetailExport;
use App\Exports\Reports\KartuStokRingkasanExport;
use App\Exports\Reports\MutasiExport;
use App\Exports\Reports\PenerimaanExport;
use App\Exports\Reports\SpoilExport;
use App\Exports\Reports\StokMenipisExport;
use App\Exports\Reports\StokSummaryExport;
use App\Http\Controllers\Controller;
use App\Modules\Core\Models\Department;
use App\Modules\Core\Models\Outlet;
use App\Modules\Inventory\Models\Item;
use App\Modules\Inventory\Models\ItemCategory;
use App\Modules\Stock\Models\StockMutation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReportController extends Controller
{
    public function index(): View
    {
        return view('laporan.index');
    }

    public function mutationReport(Request $request): View
    {
        $tenantId = $this->tenantId($request);
        $filters = $this->enforceOutletScope($request, $this->validateMutationFilters($request, $tenantId));
        [$dateFrom, $dateTo] = $this->dateRange($filters);

        $base = $this->mutationQuery($tenantId, $filters, $dateFrom, $dateTo);
        $summary = (clone $base)
            ->selectRaw('
                COALESCE(SUM(CASE WHEN sm.qty_change > 0 THEN sm.qty_change ELSE 0 END), 0) as total_in,
                COALESCE(SUM(CASE WHEN sm.qty_change < 0 THEN sm.qty_change ELSE 0 END), 0) as total_out,
                COALESCE(SUM(sm.qty_change), 0) as net_qty
            ')
            ->first();

        $mutations = $base
            ->select([
                'sm.performed_at',
                'sm.mutation_type',
                'sm.stock_target',
                'sm.qty_change',
                'sm.balance_after',
                'sm.reference_type',
                'sm.reference_id',
                'sm.notes',
                'i.name as item_name',
                'i.canonical_sku',
                'o.name as outlet_name',
                'u.abbreviation as unit',
            ])
            ->orderByDesc('sm.performed_at')
            ->paginate(50)
            ->withQueryString();

        return view('laporan.mutasi', [
            'mutations' => $mutations,
            'summary' => $summary,
            'outlets' => $this->outlets($tenantId),
            'items' => $this->items($tenantId),
            'mutationTypes' => $this->mutationTypes(),
            'filters' => $filters,
        ]);
    }

    public function spoilReport(Request $request): View
    {
        $tenantId = $this->tenantId($request);
        $filters = $this->enforceOutletScope($request, $this->validateSpoilFilters($request, $tenantId));
        [$dateFrom, $dateTo] = $this->dateRange($filters);

        $base = $this->spoilQuery($tenantId, $filters, $dateFrom, $dateTo);
        $summary = (clone $base)
            ->selectRaw('
                COUNT(sw.id) as total_rows,
                COALESCE(SUM(sw.qty_in_base_unit), 0) as total_qty_base,
                COALESCE(SUM(CASE WHEN sw.is_duplicate_photo = 1 THEN 1 ELSE 0 END), 0) as duplicate_photos
            ')
            ->first();

        $spoils = $base
            ->select([
                'sw.recorded_at',
                'sw.qty',
                'sw.qty_in_base_unit',
                'sw.reason_category',
                'sw.status',
                'sw.approval_status',
                'sw.photo',
                'sw.photo_path',
                'sw.is_duplicate_photo',
                'i.name as item_name',
                'i.canonical_sku',
                'o.name as outlet_name',
                'd.name as department_name',
                'u.abbreviation as unit',
            ])
            ->orderByDesc('sw.recorded_at')
            ->paginate(50)
            ->withQueryString();

        return view('laporan.spoil', [
            'spoils' => $spoils,
            'summary' => $summary,
            'outlets' => $this->outlets($tenantId),
            'departments' => Department::query()->where('tenant_id', $tenantId)->orderBy('name')->get(),
            'filters' => $filters,
        ]);
    }

    public function receivingReport(Request $request): View
    {
        $tenantId = $this->tenantId($request);
        $filters = $this->enforceOutletScope($request, $this->validateReceivingFilters($request, $tenantId));
        [$dateFrom, $dateTo] = $this->dateRange($filters);

        $base = $this->receivingQuery($tenantId, $filters, $dateFrom, $dateTo);
        $summary = (clone $base)
            ->selectRaw('
                COUNT(DISTINCT gr.id) as total_receipts,
                COUNT(gri.id) as total_items,
                COALESCE(SUM(gri.total_value), 0) as total_value
            ')
            ->first();

        $receivings = $base
            ->select([
                'gr.code',
                'gr.receipt_date',
                'gr.source',
                'gr.supplier_name',
                'gr.vendor_name',
                'gr.doc_number',
                'gr.status',
                'o.name as outlet_name',
                'i.name as item_name',
                'i.canonical_sku',
                'u.abbreviation as unit',
                'gri.qty_received',
                'gri.qty_in_base_unit',
                'gri.unit_price',
                'gri.total_value',
            ])
            ->orderByDesc('gr.receipt_date')
            ->orderByDesc('gr.id')
            ->paginate(50)
            ->withQueryString();

        return view('laporan.penerimaan', [
            'receivings' => $receivings,
            'summary' => $summary,
            'outlets' => $this->outlets($tenantId),
            'sources' => $this->receivingSources(),
            'filters' => $filters,
        ]);
    }

    public function stockSummary(Request $request): View|RedirectResponse
    {
        if (! $request->user()->can('view_all_reports')) {
            return redirect()->route('stock.balance.index');
        }

        $tenantId = $this->tenantId($request);
        $filters = $request->validate([
            'brand_id' => ['nullable', 'integer'],
            'outlet_id' => [
                'nullable',
                'integer',
                Rule::exists('outlets', 'id')->where('tenant_id', $tenantId),
            ],
            'category_id' => [
                'nullable',
                'integer',
                Rule::exists('item_categories', 'id')->where('tenant_id', $tenantId),
            ],
        ]);

        $outlets = DB::table('outlets')
            ->where('tenant_id', $tenantId)
            ->where('status', 'ACTIVE')
            ->when($filters['brand_id'] ?? null, fn ($query, $brandId) => $query->where('brand_id', $brandId))
            ->when($filters['outlet_id'] ?? null, fn ($query, $outletId) => $query->where('id', $outletId))
            ->orderBy('name')
            ->get(['id', 'name', 'brand_id']);

        $outletIds = $outlets->pluck('id')->all();

        $outletCards = DB::table('stock_balances as sb')
            ->join('outlets as o', 'o.id', '=', 'sb.outlet_id')
            ->where('sb.tenant_id', $tenantId)
            ->whereIn('sb.outlet_id', $outletIds)
            ->selectRaw('
                o.id as outlet_id,
                o.name as outlet_name,
                COUNT(DISTINCT sb.item_id) as total_items,
                COALESCE(SUM(sb.total_value), 0) as total_value,
                COALESCE(SUM(CASE WHEN sb.qty_on_hand <= 0 THEN 1 ELSE 0 END), 0) as empty_items
            ')
            ->groupBy('o.id', 'o.name')
            ->orderBy('o.name')
            ->get()
            ->keyBy('outlet_id');

        $breakdown = DB::table('stock_balances as sb')
            ->join('items as i', 'i.id', '=', 'sb.item_id')
            ->join('outlets as o', 'o.id', '=', 'sb.outlet_id')
            ->leftJoin('item_categories as ic', 'ic.id', '=', 'i.item_category_id')
            ->where('sb.tenant_id', $tenantId)
            ->whereIn('sb.outlet_id', $outletIds)
            ->where('sb.qty_on_hand', '>', 0)
            ->when($filters['category_id'] ?? null, fn ($query, $categoryId) => $query->where('i.item_category_id', $categoryId))
            ->selectRaw('
                o.id as outlet_id,
                o.name as outlet_name,
                COALESCE(ic.name, \'Tanpa Kategori\') as category,
                COUNT(DISTINCT sb.item_id) as total_items,
                COALESCE(SUM(sb.total_value), 0) as total_value
            ')
            ->groupBy('o.id', 'o.name', 'ic.id', 'ic.name')
            ->orderBy('category')
            ->orderBy('o.name')
            ->get();

        return view('laporan.stok-summary', [
            'outlets' => $outlets,
            'outletCards' => $outletCards,
            'breakdown' => $breakdown,
            'categories' => ItemCategory::query()->where('tenant_id', $tenantId)->where('is_active', true)->orderBy('name')->get(),
            'brands' => DB::table('brands')->where('tenant_id', $tenantId)->where('status', 'ACTIVE')->orderBy('name')->get(['id', 'name']),
            'filters' => $filters,
        ]);
    }

    public function stokMenipis(Request $request): View
    {
        $tenantId = $this->tenantId($request);

        $filters = $this->enforceOutletScope($request, $request->validate([
            'outlet_id'   => ['nullable', 'integer', Rule::exists('outlets', 'id')->where('tenant_id', $tenantId)],
            'category_id' => ['nullable', 'integer', Rule::exists('item_categories', 'id')->where('tenant_id', $tenantId)],
        ]));

        $lowStockItems = DB::table('stock_balances as sb')
            ->join('items as i', 'i.id', '=', 'sb.item_id')
            ->join('outlets as o', 'o.id', '=', 'sb.outlet_id')
            ->leftJoin('units as u', 'u.id', '=', 'i.inventory_unit_id')
            ->leftJoin('item_categories as ic', 'ic.id', '=', 'i.item_category_id')
            ->where('sb.tenant_id', $tenantId)
            ->where('i.is_active', true)
            ->where('i.min_stock', '>', 0)
            ->whereRaw('sb.qty_on_hand < i.min_stock')
            ->when($filters['outlet_id'] ?? null, fn ($q, $id) => $q->where('sb.outlet_id', $id))
            ->when($filters['category_id'] ?? null, fn ($q, $id) => $q->where('i.item_category_id', $id))
            ->selectRaw('
                i.canonical_sku,
                i.name as item_name,
                i.min_stock,
                sb.qty_on_hand,
                o.name as outlet_name,
                COALESCE(u.abbreviation, \'pcs\') as unit,
                COALESCE(ic.name, \'Tanpa Kategori\') as category,
                ROUND(i.min_stock - sb.qty_on_hand, 6) as kekurangan
            ')
            ->orderByRaw('(i.min_stock - sb.qty_on_hand) DESC')
            ->orderBy('o.name')
            ->get();

        return view('laporan.stok-menipis', [
            'lowStockItems' => $lowStockItems,
            'outlets'       => $this->outlets($tenantId),
            'categories'    => ItemCategory::query()->where('tenant_id', $tenantId)->where('is_active', true)->orderBy('name')->get(),
            'filters'       => $filters,
        ]);
    }

    /**
     * Kartu Stok — ringkasan semua item (saldo awal, masuk, keluar, saldo
     * akhir) per outlet dalam 1 rentang tanggal. Beda dari Laporan Mutasi
     * (daftar mentah kronologis SEMUA item tercampur): ini per-item,
     * langsung menjawab "barang apa saja yang habis" tanpa harus scroll
     * ratusan baris mutasi. Saldo dihitung dari SUM(qty_change) mentah
     * berdasarkan performed_at — BUKAN dari kolom balance_after yang
     * tersimpan (yang cuma akurat mengikuti urutan INSERT, bukan urutan
     * kronologis — jadi bisa salah kalau ada entri backdated/historis).
     */
    public function kartuStokRingkasan(Request $request): View
    {
        $tenantId = $this->tenantId($request);
        $filters = $this->enforceOutletScope($request, $request->validate([
            'outlet_id' => ['required', 'integer', Rule::exists('outlets', 'id')->where('tenant_id', $tenantId)],
            'date_from' => ['nullable', 'date'],
            'date_to'   => ['nullable', 'date'],
            'q'         => ['nullable', 'string', 'max:255'],
        ]));

        $outlets = $this->outlets($tenantId);
        $filters['outlet_id'] = $filters['outlet_id'] ?? $outlets->first()?->id;
        [$dateFrom, $dateTo] = $this->dateRange($filters);

        $rows = collect();

        if ($filters['outlet_id']) {
            $rows = $this->kartuStokSummaryQuery($tenantId, (int) $filters['outlet_id'], $dateFrom, $dateTo, $filters['q'] ?? null);
        }

        return view('laporan.kartu-stok-ringkasan', [
            'rows' => $rows,
            'outlets' => $outlets,
            'filters' => $filters,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
        ]);
    }

    public function exportKartuStokRingkasan(Request $request): BinaryFileResponse
    {
        $tenantId = $this->tenantId($request);
        $filters = $this->enforceOutletScope($request, $request->validate([
            'outlet_id' => ['required', 'integer', Rule::exists('outlets', 'id')->where('tenant_id', $tenantId)],
            'date_from' => ['nullable', 'date'],
            'date_to'   => ['nullable', 'date'],
            'q'         => ['nullable', 'string', 'max:255'],
        ]));
        [$dateFrom, $dateTo] = $this->dateRange($filters);

        $rows = $this->kartuStokSummaryQuery($tenantId, (int) $filters['outlet_id'], $dateFrom, $dateTo, $filters['q'] ?? null);

        return Excel::download(new KartuStokRingkasanExport($rows, $dateFrom, $dateTo), 'KartuStokRingkasan.xlsx');
    }

    /**
     * Kartu Stok — kartu 1 item: saldo awal, daftar mutasi kronologis
     * (bukan terbalik seperti Laporan Mutasi), saldo berjalan dihitung di
     * PHP (bukan pakai balance_after tersimpan), lalu saldo akhir.
     */
    public function kartuStokDetail(Request $request, Item $item): View
    {
        $tenantId = $this->tenantId($request);
        abort_unless((int) $item->tenant_id === $tenantId, 403);

        $filters = $this->enforceOutletScope($request, $request->validate([
            'outlet_id' => ['required', 'integer', Rule::exists('outlets', 'id')->where('tenant_id', $tenantId)],
            'date_from' => ['nullable', 'date'],
            'date_to'   => ['nullable', 'date'],
        ]));

        $outlets = $this->outlets($tenantId);
        $filters['outlet_id'] = $filters['outlet_id'] ?? $outlets->first()?->id;
        [$dateFrom, $dateTo] = $this->dateRange($filters);

        $card = $this->kartuStokCard($tenantId, (int) $filters['outlet_id'], $item->id, $dateFrom, $dateTo);

        return view('laporan.kartu-stok-detail', array_merge($card, [
            'item' => $item,
            'outlets' => $outlets,
            'filters' => $filters,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
        ]));
    }

    public function exportKartuStokDetail(Request $request, Item $item): BinaryFileResponse
    {
        $tenantId = $this->tenantId($request);
        abort_unless((int) $item->tenant_id === $tenantId, 403);

        $filters = $this->enforceOutletScope($request, $request->validate([
            'outlet_id' => ['required', 'integer', Rule::exists('outlets', 'id')->where('tenant_id', $tenantId)],
            'date_from' => ['nullable', 'date'],
            'date_to'   => ['nullable', 'date'],
        ]));
        [$dateFrom, $dateTo] = $this->dateRange($filters);

        $card = $this->kartuStokCard($tenantId, (int) $filters['outlet_id'], $item->id, $dateFrom, $dateTo);

        return Excel::download(new KartuStokDetailExport($item, $card, $dateFrom, $dateTo), 'KartuStok-'.$item->canonical_sku.'.xlsx');
    }

    public function exportMutasi(Request $request): BinaryFileResponse
    {
        $tenantId = $this->tenantId($request);
        $filters = $this->enforceOutletScope($request, $this->validateMutationFilters($request, $tenantId));

        return Excel::download(new MutasiExport($tenantId, $filters), 'LaporanMutasiStok.xlsx');
    }

    public function exportSpoil(Request $request): BinaryFileResponse
    {
        $tenantId = $this->tenantId($request);
        $filters = $this->enforceOutletScope($request, $this->validateSpoilFilters($request, $tenantId));

        return Excel::download(new SpoilExport($tenantId, $filters), 'LaporanSpoilWaste.xlsx');
    }

    public function exportPenerimaan(Request $request): BinaryFileResponse
    {
        $tenantId = $this->tenantId($request);
        $filters = $this->enforceOutletScope($request, $this->validateReceivingFilters($request, $tenantId));

        return Excel::download(new PenerimaanExport($tenantId, $filters), 'LaporanPenerimaanBarang.xlsx');
    }

    public function exportStokSummary(Request $request): BinaryFileResponse
    {
        abort_unless($request->user()->can('view_all_reports'), 403);

        $tenantId = $this->tenantId($request);
        $filters = $request->validate([
            'brand_id'    => ['nullable', 'integer'],
            'outlet_id'   => ['nullable', 'integer', Rule::exists('outlets', 'id')->where('tenant_id', $tenantId)],
            'category_id' => ['nullable', 'integer', Rule::exists('item_categories', 'id')->where('tenant_id', $tenantId)],
        ]);

        return Excel::download(new StokSummaryExport($tenantId, $filters), 'RingkasanStokSemuaOutlet.xlsx');
    }

    public function exportStokMenipis(Request $request): BinaryFileResponse
    {
        $tenantId = $this->tenantId($request);
        $filters = $this->enforceOutletScope($request, $request->validate([
            'outlet_id'   => ['nullable', 'integer', Rule::exists('outlets', 'id')->where('tenant_id', $tenantId)],
            'category_id' => ['nullable', 'integer', Rule::exists('item_categories', 'id')->where('tenant_id', $tenantId)],
        ]));

        return Excel::download(new StokMenipisExport($tenantId, $filters), 'LaporanStokMenipis.xlsx');
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function mutationQuery(int $tenantId, array $filters, Carbon $dateFrom, Carbon $dateTo): \Illuminate\Database\Query\Builder
    {
        return DB::table('stock_mutations as sm')
            ->join('items as i', 'i.id', '=', 'sm.item_id')
            ->join('outlets as o', 'o.id', '=', 'sm.outlet_id')
            ->join('units as u', 'u.id', '=', 'sm.unit_id')
            ->where('sm.tenant_id', $tenantId)
            ->whereBetween('sm.performed_at', [$dateFrom, $dateTo])
            ->when($filters['outlet_id'] ?? null, fn ($query, $outletId) => $query->where('sm.outlet_id', $outletId))
            ->when($filters['item_id'] ?? null, fn ($query, $itemId) => $query->where('sm.item_id', $itemId))
            ->when($filters['mutation_type'] ?? null, fn ($query, $type) => $query->where('sm.mutation_type', $type))
            ->when($filters['q'] ?? null, function ($query, $search): void {
                $like = "%{$search}%";
                $query->where(function ($inner) use ($like): void {
                    $inner->where('i.name', 'like', $like)
                        ->orWhere('i.canonical_sku', 'like', $like);
                });
            });
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function spoilQuery(int $tenantId, array $filters, Carbon $dateFrom, Carbon $dateTo): \Illuminate\Database\Query\Builder
    {
        return DB::table('spoil_wastes as sw')
            ->join('items as i', 'i.id', '=', 'sw.item_id')
            ->join('outlets as o', 'o.id', '=', 'sw.outlet_id')
            ->leftJoin('departments as d', 'd.id', '=', 'sw.department_id')
            ->join('units as u', 'u.id', '=', 'sw.unit_id')
            ->where('sw.tenant_id', $tenantId)
            ->whereBetween('sw.recorded_date', [$dateFrom->toDateString(), $dateTo->toDateString()])
            ->when($filters['outlet_id'] ?? null, fn ($query, $outletId) => $query->where('sw.outlet_id', $outletId))
            ->when($filters['department_id'] ?? null, fn ($query, $departmentId) => $query->where('sw.department_id', $departmentId));
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function receivingQuery(int $tenantId, array $filters, Carbon $dateFrom, Carbon $dateTo): \Illuminate\Database\Query\Builder
    {
        return DB::table('goods_receipts as gr')
            ->join('goods_receipt_items as gri', 'gri.goods_receipt_id', '=', 'gr.id')
            ->join('items as i', 'i.id', '=', 'gri.item_id')
            ->join('outlets as o', 'o.id', '=', 'gr.outlet_id')
            ->join('units as u', 'u.id', '=', 'gri.unit_id')
            ->where('gr.tenant_id', $tenantId)
            ->whereBetween('gr.receipt_date', [$dateFrom->toDateString(), $dateTo->toDateString()])
            ->when($filters['outlet_id'] ?? null, fn ($query, $outletId) => $query->where('gr.outlet_id', $outletId))
            ->when($filters['source'] ?? null, fn ($query, $source) => $query->where('gr.source', $source));
    }

    /**
     * @return array<string, mixed>
     */
    private function validateMutationFilters(Request $request, int $tenantId): array
    {
        return $request->validate([
            'outlet_id' => ['nullable', 'integer', Rule::exists('outlets', 'id')->where('tenant_id', $tenantId)],
            'item_id' => ['nullable', 'integer', Rule::exists('items', 'id')->where('tenant_id', $tenantId)],
            'mutation_type' => ['nullable', 'string', Rule::in(array_keys($this->mutationTypes()))],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'q' => ['nullable', 'string', 'max:255'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateSpoilFilters(Request $request, int $tenantId): array
    {
        return $request->validate([
            'outlet_id' => ['nullable', 'integer', Rule::exists('outlets', 'id')->where('tenant_id', $tenantId)],
            'department_id' => ['nullable', 'integer', Rule::exists('departments', 'id')->where('tenant_id', $tenantId)],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateReceivingFilters(Request $request, int $tenantId): array
    {
        return $request->validate([
            'outlet_id' => ['nullable', 'integer', Rule::exists('outlets', 'id')->where('tenant_id', $tenantId)],
            'source' => ['nullable', 'string', Rule::in(array_keys($this->receivingSources()))],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{0: Carbon, 1: Carbon}
     */
    private function dateRange(array $filters): array
    {
        $dateFrom = isset($filters['date_from'])
            ? Carbon::parse($filters['date_from'])->startOfDay()
            : Carbon::now()->startOfMonth();

        $dateTo = isset($filters['date_to'])
            ? Carbon::parse($filters['date_to'])->endOfDay()
            : Carbon::now()->endOfDay();

        return [$dateFrom, $dateTo];
    }

    private function tenantId(Request $request): int
    {
        $tenantId = $request->user()?->tenant_id;
        abort_unless($tenantId, 403);

        return (int) $tenantId;
    }

    /**
     * Laporan Mutasi/Spoil/Penerimaan/Stok Menipis dulu cuma memfilter
     * tenant_id — outlet_id diperlakukan murni sebagai dropdown pilihan
     * bebas, jadi siapapun yang punya view_reports (bukan cuma
     * view_all_reports) bisa lihat/export data outlet MANAPUN di tenant
     * itu lewat query string, bukan cuma outlet sendiri. Paksa outlet_id
     * ke outlet user kalau user terikat 1 outlet dan tidak punya
     * view_all_reports — menimpa apapun yang dikirim di request, bukan
     * cuma default saat kosong.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function enforceOutletScope(Request $request, array $filters): array
    {
        $user = $request->user();

        if ($user?->outlet_id && ! $user->can('view_all_reports')) {
            $filters['outlet_id'] = (int) $user->outlet_id;
        }

        return $filters;
    }

    /**
     * @return \Illuminate\Support\Collection<int, object>
     */
    private function kartuStokSummaryQuery(int $tenantId, int $outletId, Carbon $dateFrom, Carbon $dateTo, ?string $search): \Illuminate\Support\Collection
    {
        $rows = DB::table('items as i')
            ->leftJoin('units as u', 'u.id', '=', 'i.inventory_unit_id')
            ->leftJoin('stock_mutations as sm', function ($join) use ($tenantId, $outletId): void {
                $join->on('sm.item_id', '=', 'i.id')
                    ->where('sm.tenant_id', $tenantId)
                    ->where('sm.outlet_id', $outletId);
            })
            ->where('i.tenant_id', $tenantId)
            ->where('i.is_active', true)
            ->when($search, fn ($q) => $q->where(function ($q) use ($search): void {
                $q->where('i.name', 'like', "%{$search}%")->orWhere('i.canonical_sku', 'like', "%{$search}%");
            }))
            ->selectRaw('
                i.id as item_id,
                i.name as item_name,
                i.canonical_sku,
                u.abbreviation as unit,
                COALESCE(SUM(CASE WHEN sm.performed_at < ? THEN sm.qty_change ELSE 0 END), 0) as saldo_awal,
                COALESCE(SUM(CASE WHEN sm.performed_at BETWEEN ? AND ? AND sm.qty_change > 0 THEN sm.qty_change ELSE 0 END), 0) as total_masuk,
                COALESCE(SUM(CASE WHEN sm.performed_at BETWEEN ? AND ? AND sm.qty_change < 0 THEN sm.qty_change ELSE 0 END), 0) as total_keluar,
                COUNT(CASE WHEN sm.performed_at BETWEEN ? AND ? THEN sm.id END) as jumlah_transaksi
            ', [$dateFrom, $dateFrom, $dateTo, $dateFrom, $dateTo, $dateFrom, $dateTo])
            ->groupBy('i.id', 'i.name', 'i.canonical_sku', 'u.abbreviation')
            ->havingRaw('saldo_awal <> 0 OR total_masuk <> 0 OR total_keluar <> 0')
            ->orderBy('i.name')
            ->get();

        return $rows->map(function ($row) {
            $row->saldo_akhir = bcadd(bcadd((string) $row->saldo_awal, (string) $row->total_masuk, 6), (string) $row->total_keluar, 6);

            return $row;
        });
    }

    /**
     * @return array{saldo_awal: string, saldo_akhir: string, mutations: \Illuminate\Support\Collection<int, object>}
     */
    private function kartuStokCard(int $tenantId, int $outletId, int $itemId, Carbon $dateFrom, Carbon $dateTo): array
    {
        $saldoAwal = (string) (DB::table('stock_mutations')
            ->where('tenant_id', $tenantId)
            ->where('outlet_id', $outletId)
            ->where('item_id', $itemId)
            ->where('performed_at', '<', $dateFrom)
            ->sum('qty_change') ?: '0.000000');

        $mutations = DB::table('stock_mutations as sm')
            ->where('sm.tenant_id', $tenantId)
            ->where('sm.outlet_id', $outletId)
            ->where('sm.item_id', $itemId)
            ->whereBetween('sm.performed_at', [$dateFrom, $dateTo])
            ->orderBy('sm.performed_at')
            ->orderBy('sm.id')
            ->select(['sm.performed_at', 'sm.mutation_type', 'sm.stock_target', 'sm.qty_change', 'sm.reference_type', 'sm.reference_id', 'sm.notes'])
            ->get();

        $running = $saldoAwal;
        $mutations = $mutations->map(function ($row) use (&$running) {
            $running = bcadd($running, (string) $row->qty_change, 6);
            $row->saldo_berjalan = $running;
            $row->mutation_type_label = $this->allMutationTypeLabels()[$row->mutation_type] ?? $row->mutation_type;

            return $row;
        });

        return [
            'saldo_awal' => $saldoAwal,
            'saldo_akhir' => $running,
            'mutations' => $mutations,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function allMutationTypeLabels(): array
    {
        return $this->mutationTypes() + [
            StockMutation::TYPE_TRANSFER_OUT => 'Transfer Keluar',
            StockMutation::TYPE_TRANSFER_IN => 'Transfer Masuk',
            StockMutation::TYPE_POS_SALE => 'Penjualan POS',
        ];
    }

    private function outlets(int $tenantId): \Illuminate\Database\Eloquent\Collection
    {
        return Outlet::query()
            ->where('tenant_id', $tenantId)
            ->where('status', 'ACTIVE')
            ->orderBy('name')
            ->get();
    }

    private function items(int $tenantId): \Illuminate\Database\Eloquent\Collection
    {
        return Item::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->orderBy('name')
            ->limit(200)
            ->get(['id', 'canonical_sku', 'name']);
    }

    /**
     * @return array<string, string>
     */
    private function mutationTypes(): array
    {
        return [
            StockMutation::TYPE_OPEN_STOCK => 'Open Stock',
            StockMutation::TYPE_GOODS_RECEIVE => 'Goods Receive',
            StockMutation::TYPE_PO_RECEIVE => 'PO Receive',
            StockMutation::TYPE_SPOIL_WASTE => 'Spoil Waste',
            StockMutation::TYPE_DAILY_OPNAME_ADJ => 'Daily Opname Adj',
            StockMutation::TYPE_MONTHLY_OPNAME_ADJ => 'Monthly Opname Adj',
            StockMutation::TYPE_VOID_REVERSAL => 'Void Reversal',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function receivingSources(): array
    {
        return [
            'OCIA_PO' => 'Kopi OCIA',
            'WIP_CENTRAL_KITCHEN' => 'WIP Central Kitchen',
            'PURCHASING_DRYGOOD' => 'Drygood Purchasing',
            'SUPPLIER_LUAR' => 'Supplier Luar',
        ];
    }
}
