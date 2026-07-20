<?php

namespace App\Policies;

use App\Models\TicketSubtask;
use App\Models\User;

/**
 * Subtasks inherit their ticket's visibility, then need subtasks.manage on top
 * (F08). Almost everyone has that permission — a subtask is planning, and the
 * people doing the work are the ones who know how it splits.
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
            && $user->can('view', $ticket)
            && ! $ticket->isLocked();
    }

    public function delete(User $user, TicketSubtask $subtask, ?\App\Models\Ticket $ticket = null): bool
    {
        return $this->update($user, $subtask, $ticket);
    }
}
