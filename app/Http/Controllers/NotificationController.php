<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HasPerPageSelector;
use App\Http\Requests\BulkMarkNotificationsReadRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationController extends Controller
{
    use HasPerPageSelector;

    public function index(Request $request): View
    {
        [$perPage, $perPageOptions] = $this->perPageAndOptions($request, 20);

        $notifications = $request->user()
            ->notifications()
            ->latest()
            ->paginate($perPage)
            ->withQueryString();

        return view('notifications.index', compact('notifications', 'perPage', 'perPageOptions'));
    }

    public function open(Request $request, string $id): RedirectResponse
    {
        $notification = $request->user()->notifications()->findOrFail($id);

        if (! $notification->read_at) {
            $notification->markAsRead();
        }

        return redirect($notification->data['url'] ?? route('dashboard'));
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return back()->with('success', 'Semua notifikasi ditandai sudah dibaca.');
    }

    public function bulkMarkRead(BulkMarkNotificationsReadRequest $request): RedirectResponse
    {
        $notifications = $request->user()
            ->notifications()
            ->whereIn('id', $request->input('ids', []))
            ->whereNull('read_at')
            ->get();

        $notifications->markAsRead();

        return back()->with('success', "{$notifications->count()} notifikasi ditandai sudah dibaca.");
    }
}
