<?php

namespace App\Http\Controllers\Operations;

use App\Exports\Reports\InventoryCountRecapExport;
use App\Http\Controllers\Concerns\EnforcesInventorySimpleScope;
use App\Http\Controllers\Controller;
use App\Modules\Core\Models\Department;
use App\Modules\Core\Models\Outlet;
use App\Modules\Operations\Models\InventoryCountDocument;
use App\Modules\Operations\Models\InventoryCountTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Rekap Hasil Hitung Mingguan -- READ-ONLY, bersumber MURNI dari
 * inventory_count_documents/inventory_count_lines berstatus SUBMITTED
 * (hasil hitung fisik yang sudah dikirim staf lewat form harian).
 * TIDAK PERNAH menghitung IN/OUT atau carry-forward saldo asumsi --
 * beda total dari InventoryCountMovementController (saldo mandiri,
 * sekarang di balik flag terpisah default OFF, lihat
 * docs/technical/uat-inventory-simple-2026-10-13.md).
 *
 * Dokumen yang statusnya BUKAN SUBMITTED (draft, atau pernah submitted
 * lalu direvisi kembali ke draft) TIDAK dianggap hasil final -- hari itu
 * tampil "Belum dihitung", walau riwayat revisinya ada di tabel
 * inventory_count_document_revisions. Ini SENGAJA: hanya versi submitted
 * yang BERLAKU SEKARANG yang ditampilkan, bukan menjumlah semua versi.
 */
class InventoryCountRecapController extends Controller
{
    use EnforcesInventorySimpleScope;

    public function index(Request $request): View
    {
        [$tenantId, $outletId, $departmentId, $template, $weekStart] = $this->resolveContext($request);

        $rows = $this->buildRows($tenantId, $outletId, $departmentId, $template, $weekStart);

        return view('operations.inventory-count.recap', [
            'template' => $template,
            'outlet' => Outlet::query()->find($outletId),
            'department' => Department::query()->find($departmentId),
            'weekStart' => $weekStart,
            'rows' => $rows,
            'distinctCategories' => $rows->pluck('subcategory')->unique()->sort()->values(),
            'isSuperAdmin' => $this->isSuperAdmin($request),
        ]);
    }

    public function export(Request $request)
    {
        [$tenantId, $outletId, $departmentId, $template, $weekStart] = $this->resolveContext($request);

        $rows = $this->buildRows($tenantId, $outletId, $departmentId, $template, $weekStart);

        $filename = 'RekapHasilHitungMingguan-'.$weekStart->toDateString().'-'.now()->format('His').'.xlsx';

        return Excel::download(new InventoryCountRecapExport($rows, $weekStart), $filename);
    }

    /**
     * @return array{0: int, 1: int, 2: int, 3: InventoryCountTemplate, 4: Carbon}
     */
    private function resolveContext(Request $request): array
    {
        $tenantId = $this->tenantId($request);
        $scope = $this->resolveInventorySimpleScope($request);

        abort_if(! $scope, 403, 'Akun Anda belum ditetapkan ke outlet & departemen yang sah.');
        [$outletId, $departmentId] = $scope;

        $template = InventoryCountTemplate::query()
            ->where('tenant_id', $tenantId)
            ->where(fn ($q) => $q->where('department_id', $departmentId)->orWhere('is_generic', true))
            ->when($request->filled('template_id'), fn ($q) => $q->where('id', $request->integer('template_id')))
            ->with('items.item.baseUnit')
            ->orderByDesc('is_generic')
            ->orderBy('name')
            ->firstOrFail();

        $weekStartParam = $request->date('week');
        $weekStart = ($weekStartParam ? Carbon::instance($weekStartParam) : now())
            ->copy()->startOfWeek(Carbon::MONDAY)->startOfDay();

        return [$tenantId, $outletId, $departmentId, $template, $weekStart];
    }

    /**
     * @return \Illuminate\Support\Collection<int, array>
     */
    private function buildRows(int $tenantId, int $outletId, int $departmentId, InventoryCountTemplate $template, Carbon $weekStart)
    {
        $weekEnd = $weekStart->copy()->addDays(6);

        // HANYA dokumen yang SAAT INI berstatus SUBMITTED -- dokumen draft
        // (baru/belum dikirim, atau direvisi kembali) TIDAK dianggap hasil
        // final, sesuai instruksi "draft bukan hasil final".
        $documents = InventoryCountDocument::query()
            ->where('tenant_id', $tenantId)
            ->where('outlet_id', $outletId)
            ->where('department_id', $departmentId)
            ->where('template_id', $template->id)
            ->where('status', InventoryCountDocument::STATUS_SUBMITTED)
            ->whereBetween('business_date', [$weekStart->toDateString(), $weekEnd->toDateString()])
            ->with(['lines.item', 'lines.wholeUnit', 'lines.looseUnit'])
            ->get();

        // Lookup: [businessDate][item_id][location] => line.
        $byDateItemLocation = [];
        foreach ($documents as $document) {
            $dateKey = $document->business_date->toDateString();
            foreach ($document->lines as $line) {
                $byDateItemLocation[$dateKey][$line->item_id][$line->location] = $line;
            }
        }

        $templateItems = $template->items()->where('is_active', true)->orderBy('sort_order')->get();

        $rows = collect();
        foreach ($templateItems as $templateItem) {
            $item = $templateItem->item;
            if (! $item) {
                continue;
            }

            $locations = $templateItem->locations ?: ['GENERIC'];
            foreach ($locations as $location) {
                $days = [];
                for ($i = 0; $i < 7; $i++) {
                    $date = $weekStart->copy()->addDays($i);
                    $dateKey = $date->toDateString();
                    $line = $byDateItemLocation[$dateKey][$item->id][$location] ?? null;

                    $days[] = [
                        'date' => $dateKey,
                        'counted' => (bool) ($line?->is_counted),
                        'qty_whole' => $line?->is_counted ? $this->formatQty($line->qty_whole) : null,
                        'qty_loose' => $line?->is_counted && $line->qty_loose !== null ? $this->formatQty($line->qty_loose) : null,
                        'whole_unit' => $line?->wholeUnit?->abbreviation ?? $line?->wholeUnit?->code,
                        'loose_unit' => $line?->looseUnit?->abbreviation ?? $line?->looseUnit?->code,
                        'config_valid' => $line ? (bool) $line->config_valid : true,
                    ];
                }

                $rows->push([
                    'item' => $item,
                    'location' => $location,
                    'subcategory' => $templateItem->subcategory_label ?: 'Lainnya',
                    'days' => $days,
                ]);
            }
        }

        return $rows;
    }

    private function tenantId(Request $request): int
    {
        $tenantId = $request->user()?->tenant_id;
        abort_unless($tenantId, 403, 'Tenant belum terpasang pada user.');

        return (int) $tenantId;
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
}
