<?php

namespace App\Http\Controllers\Operations;

use App\Exports\Templates\OpnameMonthlyImportTemplate;
use App\Http\Controllers\Controller;
use App\Imports\OpnameMonthlyImport;
use App\Jobs\ProcessOpnameMonthlyImportJob;
use App\Models\MonthlyImportRun;
use App\Modules\Core\Models\Department;
use App\Modules\Core\Models\Outlet;
use App\Modules\Operations\Models\OpnameSession;
use App\Services\OpnameService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Import Opname Bulanan: terpisah dari OpnameController yang sudah besar.
 * Kebijakan bulk cross-scope write sama seperti import historis Spoil yang
 * sudah ada -- digerbang permission approve_opname DAN hard-restrict ke
 * SUPER_ADMIN (efeknya setara submit+approve banyak sesi sekaligus).
 */
class OpnameMonthlyImportController extends Controller
{
    public function form(Request $request): View
    {
        $tenantId = $this->tenantId($request);
        abort_unless($this->isSuperAdmin($request), 403, 'Import Opname Bulanan cuma bisa dilakukan Super Admin.');

        return view('operations.opname.import-bulanan', [
            'outlets' => Outlet::query()->where('tenant_id', $tenantId)->where('status', 'ACTIVE')->orderBy('name')->get(),
            'departments' => Department::query()->where('tenant_id', $tenantId)->where('status', 'ACTIVE')->orderBy('name')->get(),
        ]);
    }

    public function template(Request $request, OpnameService $opnameService)
    {
        $tenantId = $this->tenantId($request);
        abort_unless($this->isSuperAdmin($request), 403, 'Import Opname Bulanan cuma bisa dilakukan Super Admin.');

        $validated = $this->validateScope($request, $tenantId);
        $departmentId = (int) $validated['department_id'];

        $items = $opnameService->itemsForOpname($tenantId, (int) $validated['outlet_id'], OpnameSession::TYPE_DAILY, $departmentId);
        $dates = $this->datesInMonth($validated['month']);

        return Excel::download(
            new OpnameMonthlyImportTemplate($items, $dates, $departmentId),
            "Template-Opname-Bulanan-{$validated['month']}.xlsx"
        );
    }

    public function preview(Request $request): JsonResponse
    {
        $tenantId = $this->tenantId($request);
        abort_unless($this->isSuperAdmin($request), 403, 'Import Opname Bulanan cuma bisa dilakukan Super Admin.');

        $validated = $this->validateScope($request, $tenantId);
        $validated['file'] = $request->validate(['file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:10240']])['file'];

        $outletId = (int) $validated['outlet_id'];
        $departmentId = (int) $validated['department_id'];

        $path = $validated['file']->store("tmp-imports/{$tenantId}/{$request->user()->id}", 'local');

        $import = new OpnameMonthlyImport();
        Excel::import($import, Storage::disk('local')->path($path));
        $rows = $import->preview($tenantId, $outletId, $departmentId);

        $token = (string) Str::uuid();
        Cache::put("monthly-import-upload:{$token}", [
            'tenant_id' => $tenantId,
            'user_id' => (int) $request->user()->id,
            'path' => $path,
            'outlet_id' => $outletId,
            'department_id' => $departmentId,
            'month' => $validated['month'],
        ], now()->addHours(2));

        return response()->json(['token' => $token, 'rows' => $rows]);
    }

    public function apply(Request $request): JsonResponse
    {
        $tenantId = $this->tenantId($request);
        abort_unless($this->isSuperAdmin($request), 403, 'Import Opname Bulanan cuma bisa dilakukan Super Admin.');

        $validated = $request->validate([
            'token' => ['required', 'string'],
            'selected_dates' => ['required', 'array', 'min:1'],
            'selected_dates.*' => ['date_format:Y-m-d'],
        ]);

        $cacheKey = "monthly-import-upload:{$validated['token']}";
        $stored = Cache::get($cacheKey);

        abort_if(
            ! $stored || (int) $stored['tenant_id'] !== $tenantId || (int) $stored['user_id'] !== (int) $request->user()->id,
            422,
            'Token tidak valid atau sudah kedaluwarsa -- silakan upload ulang filenya.'
        );

        $run = MonthlyImportRun::query()->create([
            'tenant_id' => $tenantId,
            'type' => MonthlyImportRun::TYPE_OPNAME,
            'scope' => [
                'outlet_id' => $stored['outlet_id'],
                'department_id' => $stored['department_id'],
                'month' => $stored['month'],
                'selected_dates' => array_values($validated['selected_dates']),
            ],
            'status' => MonthlyImportRun::STATUS_QUEUED,
            'upload_path' => $stored['path'],
            'created_by' => $request->user()->id,
        ]);

        Cache::forget($cacheKey);

        ProcessOpnameMonthlyImportJob::dispatch($run->id);

        return response()->json(['run_id' => $run->id]);
    }

    public function status(Request $request, MonthlyImportRun $run): JsonResponse
    {
        abort_unless((int) $run->tenant_id === $this->tenantId($request), 404);

        return response()->json([
            'status' => $run->status,
            'total_units' => $run->total_units,
            'processed_units' => $run->processed_units,
            'failed_units' => $run->failed_units,
            'errors' => $run->errors ?? [],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateScope(Request $request, int $tenantId): array
    {
        return $request->validate([
            'outlet_id' => ['required', 'integer', Rule::exists('outlets', 'id')->where('tenant_id', $tenantId)],
            'department_id' => ['required', 'integer', Rule::exists('departments', 'id')->where('tenant_id', $tenantId)],
            'month' => ['required', 'date_format:Y-m'],
        ]);
    }

    /**
     * @return list<string>
     */
    private function datesInMonth(string $yearMonth): array
    {
        $start = Carbon::createFromFormat('Y-m', $yearMonth)->startOfMonth();
        $dates = [];

        for ($i = 0; $i < $start->daysInMonth; $i++) {
            $dates[] = $start->copy()->addDays($i)->toDateString();
        }

        return $dates;
    }

    private function isSuperAdmin(Request $request): bool
    {
        return (bool) $request->user()?->hasRole('SUPER_ADMIN');
    }

    private function tenantId(Request $request): int
    {
        $tenantId = $request->user()?->tenant_id;

        abort_unless($tenantId, 403, 'Tenant belum terpasang pada user.');

        return (int) $tenantId;
    }
}
