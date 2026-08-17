<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Core\Models\ActivityLogEntry;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        $tenantId = (int) $request->user()->tenant_id;

        $entries = ActivityLogEntry::query()
            ->where('tenant_id', $tenantId)
            ->with(['causer', 'subject'])
            ->when($request->get('causer_id'), fn ($q, $id) => $q->where('causer_id', $id)->where('causer_type', User::class))
            ->when($request->get('log_name'), fn ($q, $log) => $q->where('log_name', $log))
            ->when($request->get('event'), fn ($q, $event) => $q->where('event', $event))
            ->when($request->get('q'), fn ($q, $search) => $q->where('description', 'like', "%{$search}%"))
            ->when($request->get('date_from'), fn ($q, $from) => $q->whereDate('created_at', '>=', $from))
            ->when($request->get('date_to'), fn ($q, $to) => $q->whereDate('created_at', '<=', $to))
            ->latest('id')
            ->paginate(30)
            ->withQueryString();

        return view('settings.audit-log.index', [
            'entries' => $entries,
            'logNames' => $this->logNames(),
            'actors' => $this->recentActors($tenantId),
            'filters' => $request->only(['causer_id', 'log_name', 'event', 'q', 'date_from', 'date_to']),
        ]);
    }

    public function show(Request $request, ActivityLogEntry $auditLog): View
    {
        abort_unless((int) $auditLog->tenant_id === (int) $request->user()->tenant_id, 403);

        return view('settings.audit-log.show', ['entry' => $auditLog->load(['causer', 'subject'])]);
    }

    /**
     * @return array<string, string>
     */
    private function logNames(): array
    {
        return [
            'auth' => 'Login/Logout',
            'user' => 'User & Role',
            'master-data' => 'Data Master',
            'procurement' => 'Purchase Order',
            'receiving' => 'Penerimaan Barang',
            'production' => 'Menu & Resep',
            'operations' => 'Opname & Spoil',
            'stock' => 'Stok & Transfer',
        ];
    }

    /**
     * @return Collection<int, User>
     */
    private function recentActors(int $tenantId): Collection
    {
        return User::query()->where('tenant_id', $tenantId)->orderBy('name')->get(['id', 'name']);
    }
}
