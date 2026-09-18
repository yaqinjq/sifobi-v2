<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Concerns\HasPerPageSelector;
use App\Http\Controllers\Controller;
use App\Modules\Core\Models\Member;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MemberController extends Controller
{
    use HasPerPageSelector;

    public function index(Request $request): View
    {
        $tenantId = $this->tenantId($request);
        $search = $request->string('search')->toString();
        [$perPage, $perPageOptions] = $this->perPageAndOptions($request, 20);

        $members = Member::query()
            ->where('tenant_id', $tenantId)
            ->when($search, fn ($q) => $q->where(fn ($q2) => $q2
                ->where('name', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%")))
            ->orderByDesc('points_balance')
            ->paginate($perPage)
            ->withQueryString();

        return view('settings.members.index', compact('members', 'search', 'perPage', 'perPageOptions'));
    }

    public function show(Request $request, Member $member): View
    {
        $tenantId = $this->tenantId($request);

        abort_unless((int) $member->tenant_id === $tenantId, 403);

        $member->load(['pointTransactions' => fn ($q) => $q->with('order:id,order_number')]);

        return view('settings.members.show', compact('member'));
    }

    private function tenantId(Request $request): int
    {
        $tenantId = $request->user()?->tenant_id;

        abort_unless($tenantId, 403, 'Tenant belum terpasang pada user.');

        return (int) $tenantId;
    }
}
