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

    /**
     * ★ (2026-08-02) Deleting is not the same act as editing, so it stopped
     * riding on update().
     *
     * Changing your subtask's status or its title is planning your own work.
     * Removing it destroys the only record that the work was ever asked for —
     * and with it the row F18 would have paid on. Same for handing it to
     * somebody else (reassign() below): both change WHO gets paid, which is a
     * different decision from how the work is going.
     *
     * Seeded to every role that already had subtasks.manage, so this changes
     * nothing on day one. What it buys is the ability to take it off one person
     * through the per-user overrides, without also taking away their ability to
     * work.
     */
    public function delete(User $user, TicketSubtask $subtask, ?\App\Models\Ticket $ticket = null): bool
    {
        return $this->update($user, $subtask, $ticket)
            && $user->hasPermission('subtasks.reassign');
    }

    /**
     * Moving a subtask from one person to another. Checked by SubtaskController
     * only when assignee_id actually changes — editing your own subtask without
     * touching its owner never needs this.
     */
    public function reassign(User $user, TicketSubtask $subtask, ?\App\Models\Ticket $ticket = null): bool
    {
        return $this->update($user, $subtask, $ticket)
            && $user->hasPermission('subtasks.reassign');
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

    /**
     * ★ (2026-08-05) Setting or moving the due date — its own permission, for
     * exactly the same reason points has one.
     *
     * The due date used to be ordinary planning: any of the six roles with
     * subtasks.manage could type one, and the calendar let its owner drag it to
     * another day. That was fine while a date was only a date.
     *
     * It stopped being only a date when PointEngineService started writing a
     * subtask finished after its due date at MINUS its points. From that moment
     * the field decides money, and leaving it with the person whose money it is
     * means anyone running late can drag their own deadline forward and turn a
     * penalty back into an award. Nothing else in the system asks the person
     * being measured to set the measurement.
     *
     * So it moves to whoever plans the work rather than does it — admin and
     * manager out of the box. Everyone else still sees the date (it is a fact
     * about their subtask, and the calendar is built on it); they just can't
     * write it, the same read-only treatment points already gets.
     *
     * Deliberately NOT folded into points.rules.manage: scheduling work and
     * administering the points ledger are two different jobs, and an admin
     * should be able to hand out one without the other.
     *
     * Gates all three write paths — the subtask form, the ticket create form's
     * rows, and the calendar's drag endpoint.
     *
     * Deliberately does NOT go through update() and its owns() check, unlike
     * delete() and reassign() above. Those two are "you may do more to your own
     * subtask"; this one is the opposite kind of permission — its entire purpose
     * is reaching work that belongs to somebody else. A manager who could only
     * date their own subtasks could not schedule the team, which is the one
     * thing scheduling is for. Requiring ownership here made the calendar drag
     * 403 for the exact person the permission was created for.
     *
     * Ticket visibility and the lock still apply: this widens WHOSE subtask you
     * may date, never WHICH tickets you can touch.
     *
     * Called two ways, hence the optional arguments. With the class name
     * (`can('schedule', TicketSubtask::class)`) it answers "may this person set
     * dates at all" — what the form fields ask before rendering. With a subtask
     * it answers the full question, which is what the write paths authorize.
     */
    public function schedule(User $user, ?TicketSubtask $subtask = null, ?\App\Models\Ticket $ticket = null): bool
    {
        if (! $user->hasPermission('subtasks.schedule')) {
            return false;
        }

        if ($subtask === null) {
            return true;
        }

        $ticket ??= $subtask->ticket;

        return $user->can('view', $ticket) && ! $ticket->isLocked();
    }
}
