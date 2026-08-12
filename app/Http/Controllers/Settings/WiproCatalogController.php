<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Imports\WiproCatalogImport;
use App\Modules\Inventory\Models\Item;
use App\Modules\Inventory\Models\ItemCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Throwable;

class WiproCatalogController extends Controller
{
    public function index(Request $request): View
    {
        $tenantId = $this->tenantId($request);

        $stats = [
            'total'      => Item::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('item_source', 'WIPRO')->count(),
            'categories' => ItemCategory::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('code', 'like', 'WIPRO_%')
                ->count(),
            'last_import' => Item::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('item_source', 'WIPRO')
                ->max('updated_at'),
        ];

        return view('settings.wipro-catalog.index', compact('stats'));
    }

    public function import(Request $request): RedirectResponse
    {
        $request->validate([
            'catalog_file' => ['required', 'file', 'mimes:xlsx,xls', 'max:10240'],
        ]);

        $tenantId = $this->tenantId($request);
        $path     = $request->file('catalog_file')->store('wipro-catalog-uploads', 'local');

        try {
            $import = new WiproCatalogImport($tenantId);
            $import->import(Storage::disk('local')->path($path));
            $summary = $import->summary();
        } catch (Throwable $e) {
            Storage::disk('local')->delete($path);

            return redirect()
                ->route('settings.wipro-catalog.index')
                ->with('error', 'Import gagal: ' . $e->getMessage());
        }

        Storage::disk('local')->delete($path);

        $message = "Import selesai. Baru: {$summary['inserted']}, diperbarui: {$summary['updated']}.";

        if (! empty($summary['errors'])) {
            $errorList = implode(' | ', array_slice($summary['errors'], 0, 5));
            $more      = count($summary['errors']) > 5 ? ' (dan ' . (count($summary['errors']) - 5) . ' error lainnya)' : '';

            return redirect()
                ->route('settings.wipro-catalog.index')
                ->with('warning', $message . ' Error: ' . $errorList . $more);
        }

        return redirect()
            ->route('settings.wipro-catalog.index')
            ->with('success', $message);
    }

    private function tenantId(Request $request): int
    {
        $tenantId = $request->user()?->tenant_id;

        abort_unless($tenantId, 403, 'Tenant belum terpasang pada user.');

        return (int) $tenantId;
    }
}
