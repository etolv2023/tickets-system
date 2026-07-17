<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

/** F20 — the bell. */
class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        return view('notifications.index', [
            'notifications' => $request->user()->notifications()->paginate(30),
            'unread' => $request->user()->unreadNotifications()->count(),
        ]);
    }

    public function read(Request $request, string $id): RedirectResponse
    {
        $notification = $request->user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        $this->refreshBadge($request->user()->id);

        $ticketId = $notification->data['ticket_id'] ?? null;

        // Reading it should take you to the thing it was about.
        return $ticketId
            ? redirect()->route('tickets.show', $ticketId)
            : back();
    }

    public function readAll(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        $this->refreshBadge($request->user()->id);

        return back()->with('status', 'علّمنا الكل كمقروء.');
    }

    /**
     * The nav caches the unread count for a minute. A stale bell after the user
     * has just cleared it reads as broken, so this drops it immediately.
     */
    private function refreshBadge(int $userId): void
    {
        Cache::forget("notif.unread.{$userId}");
    }
}
