<?php

namespace App\Http\Controllers;

use App\Notifications\NotificationEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

/** F20 — the bell, and the screen behind it. */
class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->only('q', 'group', 'state');

        $notifications = $request->user()->notifications()
            // The payload is JSON in a text column, so search is a LIKE over
            // it. Acceptable here: it is scoped to one user's own rows by an
            // indexed morph key, and that set stays small.
            ->when($filters['q'] ?? null, fn ($query, $term) => $query->where('data', 'like', "%{$term}%"))
            ->when($filters['group'] ?? null, fn ($query, $group) => $query->where('data', 'like', '%"group":"' . $group . '"%'))
            ->when(($filters['state'] ?? null) === 'unread', fn ($query) => $query->whereNull('read_at'))
            ->when(($filters['state'] ?? null) === 'read', fn ($query) => $query->whereNotNull('read_at'))
            ->paginate(30)
            ->withQueryString();

        return view('notifications.index', [
            'notifications' => $notifications,
            'unread' => $this->unreadCount($request),
            'filters' => $filters,
            'groups' => NotificationEvent::groupOptions(),
        ]);
    }

    /**
     * The bell's own count, as JSON.
     *
     * Polled by the topbar. Deliberately not a websocket: real-time needs a
     * broadcast server and a decision about Redis that has not been made yet,
     * and this endpoint is precisely the seam real-time would replace — the
     * bell reads one number from one place either way.
     */
    public function count(Request $request): JsonResponse
    {
        return response()->json(['unread' => $this->unreadCount($request)]);
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

    private function unreadCount(Request $request): int
    {
        return Cache::remember(
            "notif.unread.{$request->user()->id}",
            now()->addMinute(),
            fn () => $request->user()->unreadNotifications()->count(),
        );
    }

    /**
     * The topbar caches the unread count for a minute. A stale bell after the
     * user has just cleared it reads as broken, so this drops it immediately.
     */
    private function refreshBadge(int $userId): void
    {
        Cache::forget("notif.unread.{$userId}");
    }
}
