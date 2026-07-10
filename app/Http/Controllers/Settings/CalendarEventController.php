<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Modules\Core\Models\Brand;
use App\Modules\Core\Models\CalendarEvent;
use App\Modules\Core\Models\Outlet;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CalendarEventController extends Controller
{
    public function index(Request $request): View
    {
        $tenantId = $this->tenantId($request);

        return view('settings.calendar-events.index', [
            'events' => CalendarEvent::query()
                ->with(['outlet', 'brand'])
                ->where('tenant_id', $tenantId)
                ->orderByDesc('event_date')
                ->paginate(30),
            'outlets' => Outlet::query()
                ->where('tenant_id', $tenantId)
                ->where('status', 'ACTIVE')
                ->orderBy('name')
                ->get(),
            'brands' => Brand::query()
                ->where('tenant_id', $tenantId)
                ->where('status', 'ACTIVE')
                ->orderBy('name')
                ->get(),
            'eventTypes' => CalendarEvent::TYPES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $tenantId = $this->tenantId($request);
        $validated = $this->validated($request, $tenantId);

        DB::transaction(function () use ($tenantId, $validated): void {
            CalendarEvent::query()->create([
                ...$validated,
                'tenant_id' => $tenantId,
            ]);
        });

        return back()->with('success', 'Kalender event berhasil ditambahkan.');
    }

    public function update(Request $request, CalendarEvent $calendarEvent): RedirectResponse
    {
        $tenantId = $this->tenantId($request);
        $this->authorizeTenant($calendarEvent, $tenantId);
        $validated = $this->validated($request, $tenantId);

        DB::transaction(fn () => $calendarEvent->update($validated));

        return back()->with('success', 'Kalender event berhasil diperbarui.');
    }

    public function destroy(Request $request, CalendarEvent $calendarEvent): RedirectResponse
    {
        $tenantId = $this->tenantId($request);
        $this->authorizeTenant($calendarEvent, $tenantId);

        DB::transaction(fn () => $calendarEvent->delete());

        return back()->with('success', 'Kalender event berhasil dihapus.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, int $tenantId): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'event_date' => ['required', 'date'],
            'event_end_date' => ['nullable', 'date', 'after_or_equal:event_date'],
            'event_type' => ['required', Rule::in(CalendarEvent::TYPES)],
            'demand_multiplier' => ['required', 'numeric', 'min:0.01', 'max:99.99'],
            'outlet_id' => [
                'nullable',
                'integer',
                Rule::exists('outlets', 'id')->where('tenant_id', $tenantId),
            ],
            'brand_id' => [
                'nullable',
                'integer',
                Rule::exists('brands', 'id')->where('tenant_id', $tenantId),
            ],
            'notes' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $validated['is_active'] = $request->boolean('is_active');

        return $validated;
    }

    private function tenantId(Request $request): int
    {
        $tenantId = $request->user()?->tenant_id;
        abort_unless($tenantId, 403, 'Tenant belum terpasang pada user.');

        return (int) $tenantId;
    }

    private function authorizeTenant(CalendarEvent $event, int $tenantId): void
    {
        abort_unless((int) $event->tenant_id === $tenantId, 404);
    }
}
