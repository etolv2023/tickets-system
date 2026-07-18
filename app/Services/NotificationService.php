<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\Ticket;
use App\Models\TicketSubtask;
use App\Models\User;
use App\Notifications\NotificationEvent;
use App\Notifications\TicketNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;

/**
 * Who gets told what (F20).
 *
 * Every notification goes through dispatch(). Callers say what happened; this
 * decides who hears about it. Before, each controller picked its own
 * recipients, which is how a comment reached nobody while an assignment
 * reached the same person twice.
 *
 * Two rules, enforced here so no caller can forget:
 *   1. Never notify someone about their own action. A system that pings you
 *      about what you just did trains people to ignore it.
 *   2. Never notify an inactive user.
 */
class NotificationService
{
    /**
     * The one entry point.
     *
     * @param  array<int, int>|null  $extra  ids beyond the ticket's own circle
     */
    public function dispatch(
        Ticket $ticket,
        NotificationEvent $event,
        string $message,
        ?int $actorId = null,
        ?array $extra = null,
        ?TicketSubtask $subtask = null,
    ): void {
        $recipients = $this->recipients(
            array_merge($this->circle($ticket), $extra ?? []),
            $actorId
        );

        if ($recipients->isEmpty()) {
            return;
        }

        Notification::send(
            $recipients,
            new TicketNotification($ticket, $event, $message, $actorId, $subtask?->id)
        );

        $this->clearBadges($recipients->pluck('id')->all());
    }

    /** Tell exactly one person, regardless of the ticket's circle. */
    public function dispatchTo(
        ?int $userId,
        Ticket $ticket,
        NotificationEvent $event,
        string $message,
        ?int $actorId = null,
    ): void {
        if ($userId === null) {
            return;
        }

        $recipients = $this->recipients([$userId], $actorId);

        if ($recipients->isEmpty()) {
            return;
        }

        Notification::send($recipients, new TicketNotification($ticket, $event, $message, $actorId));

        $this->clearBadges([$userId]);
    }

    /**
     * Everyone with a stake in this ticket.
     *
     * F11: the creator, the two developers and the tester are watchers whether
     * they asked or not — the ticket is about them. Explicit watchers are added
     * on top. Subtask assignees count too: holding a piece of the work involves
     * you even when no ticket column names you, which is exactly the case the
     * old recipient list missed.
     *
     * @return array<int, int>
     */
    public function circle(Ticket $ticket): array
    {
        $implicit = array_filter([
            $ticket->created_by,
            $ticket->requested_by,
            $ticket->assigned_frontend_id,
            $ticket->assigned_backend_id,
            $ticket->tester_id,
        ]);

        $explicit = $ticket->watchers()->pluck('users.id')->all();

        $workers = TicketSubtask::query()
            ->where('ticket_id', $ticket->id)
            ->whereNotNull('assignee_id')
            ->distinct()
            ->pluck('assignee_id')
            ->all();

        return array_values(array_unique(array_merge($implicit, $explicit, $workers)));
    }

    /** Older name for the circle; kept so existing call sites keep working. */
    public function watcherIds(Ticket $ticket): array
    {
        return $this->circle($ticket);
    }

    public function notifyWatchers(Ticket $ticket, string $event, string $message, ?int $actorId): void
    {
        $this->dispatch($ticket, NotificationEvent::from($event), $message, $actorId);
    }

    public function notifyUser(?int $userId, Ticket $ticket, string $event, string $message, ?int $actorId): void
    {
        $this->dispatchTo($userId, $ticket, NotificationEvent::from($event), $message, $actorId);
    }

    /** @param array<int, int> $userIds */
    private function clearBadges(array $userIds): void
    {
        foreach ($userIds as $id) {
            Cache::forget("notif.unread.{$id}");
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
