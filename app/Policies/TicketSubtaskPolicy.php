<?php

namespace App\Policies;

use App\Models\TicketSubtask;
use App\Models\User;

/**
 * Subtasks inherit their ticket's visibility, then need subtasks.manage on top
 * (F08).
 *
 * ★ (2026-08-02) …and, for an existing subtask, you have to be the person it
 * belongs to.
 *
 * subtasks.manage is held by almost every role — admin, manager, frontend,
 * backend, devops, tester — so before this, anyone who could merely SEE a
 * ticket could reassign, finish or delete any subtask on it. Support could
 * close a developer's work; a tester could hand it to somebody else. The
 * permission was answering "can this person plan work?" and being read as "can
 * this person plan ANYONE's work?".
 *
 * The split is now:
 *   - subtasks.manage      → plan your own work
 *   - subtasks.manage.any  → reach into someone else's
 *
 * The second is granted to no role at all; an admin hands it to a named person
 * through the per-user override screen. That is what "الي بيشتغل ع التاسك بس"
 * means in code — with a deliberate, auditable way out for the one person who
 * genuinely needs it.
 *
 * This class is the only gate the six subtask mutation routes go through
 * (store, update, destroy, reorder, status, schedule), so ownership is enforced
 * in one place rather than six.
 */
class TicketSubtaskPolicy
{
    public function create(User $user, \App\Models\Ticket $ticket): bool
    {
        return $user->hasPermission('subtasks.manage')
            && $user->can('view', $ticket)
            && ! $ticket->isLocked();
    }

    /**
     * The ticket is passed in rather than read off the subtask: the detail
     * screen checks this once per row, and $subtask->ticket would be a lazy load
     * per subtask — an N+1 that preventLazyLoading rightly refuses.
     */
    public function update(User $user, TicketSubtask $subtask, ?\App\Models\Ticket $ticket = null): bool
    {
        $ticket ??= $subtask->ticket;

        return $user->hasPermission('subtasks.manage')
            && $this->owns($user, $subtask)
            && $user->can('view', $ticket)
            && ! $ticket->isLocked();
    }

    public function delete(User $user, TicketSubtask $subtask, ?\App\Models\Ticket $ticket = null): bool
    {
        return $this->update($user, $subtask, $ticket);
    }

    /**
     * Whose subtask this is. An UNOWNED subtask is open to anyone who can plan
     * on the ticket — it is a step nobody has claimed, and locking it to a
     * nonexistent owner would make it uneditable forever. The moment somebody
     * takes it, it becomes theirs.
     */
    private function owns(User $user, TicketSubtask $subtask): bool
    {
        return $subtask->assignee_id === null
            || $subtask->assignee_id === $user->id
            || $user->hasPermission('subtasks.manage.any');
    }

    /**
     * Whether the subtask's points value is editable, separate from update()
     * above: everyone with subtasks.manage can plan and reassign a subtask,
     * but points feed real bonus money, so only whoever holds
     * points.rules.manage can override the flat default (2026-07-21).
     */
    public function updatePoints(User $user): bool
    {
        return $user->hasPermission('points.rules.manage');
    }
}
