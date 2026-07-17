<?php

namespace App\Services;

use App\Models\Ticket;
use App\Models\User;
use App\Notifications\TicketNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;

/**
 * Who gets told what (F20).
 *
 * The one hard rule: never notify someone about their own action. A system that
 * pings you about what you just did trains people to ignore it.
 */
class NotificationService
{
    public function notifyWatchers(Ticket $ticket, string $event, string $message, ?int $actorId): void
    {
        $this->send($this->watcherIds($ticket), $event, $message, $ticket, $actorId);
    }

    public function notifyUser(?int $userId, Ticket $ticket, string $event, string $message, ?int $actorId): void
    {
        if ($userId === null) {
            return;
        }

        $this->send([$userId], $event, $message, $ticket, $actorId);
    }

    /**
     * F11: the assignees, the tester and the reporter are watchers whether they
     * asked or not — they're the people the ticket is about.
     *
     * @return array<int, int>
     */
    public function watcherIds(Ticket $ticket): array
    {
        $implicit = array_filter([
            $ticket->created_by,
            $ticket->assigned_frontend_id,
            $ticket->assigned_backend_id,
            $ticket->tester_id,
        ]);

        $explicit = $ticket->watchers()->pluck('users.id')->all();

        return array_values(array_unique(array_merge($implicit, $explicit)));
    }

    /**
     * @param  array<int, int>  $userIds
     */
    private function send(array $userIds, string $event, string $message, Ticket $ticket, ?int $actorId): void
    {
        $recipients = $this->recipients($userIds, $actorId);

        if ($recipients->isEmpty()) {
            return;
        }

        Notification::send($recipients, new TicketNotification($ticket, $event, $message, $actorId));

        // The nav caches each user's unread count for a minute. Without this a
        // fresh notification wouldn't light the bell until the cache expired.
        foreach ($recipients as $recipient) {
            Cache::forget("notif.unread.{$recipient->id}");
        }
    }

    /** @param array<int, int> $userIds */
    private function recipients(array $userIds, ?int $actorId): Collection
    {
        // The actor is dropped here, not at the call site, so no caller can
        // forget. F20
        $ids = array_values(array_diff(array_unique($userIds), array_filter([$actorId])));

        if ($ids === []) {
            return collect();
        }

        return User::query()->active()->whereIn('id', $ids)->get();
    }
}
