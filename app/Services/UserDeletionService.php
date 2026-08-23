<?php

namespace App\Services;

use App\Models\Ticket;
use App\Models\TicketSubtask;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Taking somebody off the system without taking their work with them.
 *
 * Deleting here means a timestamp, never a DELETE. The database was built to
 * refuse the real thing — eleven RESTRICT keys point at `users` — and it was
 * right to: a leaver's name belongs on the ticket they opened, the hours they
 * logged and the points they earned. So nothing is removed and `is_active` is
 * switched off, which every existing guard already honours.
 *
 * What this class mostly does is REFUSE. A person who still holds a role on an
 * open ticket, or still owns unfinished work, cannot simply evaporate: doing so
 * would leave a ticket that looks distributed but has nobody on it, and a
 * subtask nobody is going to finish. Automatically un-assigning them was the
 * alternative and it is worse — it silently drops commitments and fires a round
 * of Discord messages about a person who is on their way out. So the answer is
 * a blocked deletion that names exactly what has to be handed over first.
 */
class UserDeletionService
{
    /**
     * Why this user cannot be deleted yet, or an empty array.
     *
     * Returned rather than thrown so the admin screen can show the whole list at
     * once — being told about one blocker, fixing it, and then being told about
     * the next one is the slowest possible way to hand over somebody's work.
     *
     * @return array<int, string>
     */
    public function blockers(User $user): array
    {
        $reasons = [];

        $tickets = $this->openTicketRoles($user);

        if ($tickets->isNotEmpty()) {
            $reasons[] = 'لسه موزّع عليه شغل على ' . $tickets->count() . ' تذكرة مفتوحة ('
                . $tickets->take(5)->implode('، ')
                . ($tickets->count() > 5 ? '، وغيرها' : '')
                . ') — وزّعها على حد تاني الأول.';
        }

        $subtasks = $this->openSubtasks($user);

        if ($subtasks->isNotEmpty()) {
            $reasons[] = 'لسه معاه ' . $subtasks->count() . ' صب تاسك مخلصتش ('
                . $subtasks->take(5)->implode('، ')
                . ($subtasks->count() > 5 ? '، وغيرها' : '')
                . ') — سلّمها لحد تاني أو قفلها الأول.';
        }

        return $reasons;
    }

    /**
     * Soft-deletes the user.
     *
     * is_active goes down in the same write, and that is the half that actually
     * takes them out of circulation: the login check, hasPermission(), and every
     * assignment picker are built on User::active(), so none of them needed to
     * learn about deletion.
     *
     * @throws DomainException when the person still owns live work
     */
    public function delete(User $user): void
    {
        $blockers = $this->blockers($user);

        if ($blockers !== []) {
            throw new DomainException(implode(' ', $blockers));
        }

        DB::transaction(function () use ($user) {
            $user->forceFill(['is_active' => false])->save();
            $user->delete();
        });
    }

    /** Puts a deleted user back, still deactivated so nobody is silently re-armed. */
    public function restore(User $user): void
    {
        $user->restore();
    }

    /**
     * Ticket numbers where this person still holds a role on an OPEN ticket.
     *
     * Closed and resolved tickets are history and hold nobody up.
     *
     * @return \Illuminate\Support\Collection<int, string>
     */
    public function openTicketRoles(User $user): \Illuminate\Support\Collection
    {
        return Ticket::query()
            ->whereIn('status', fn ($q) => $q->select('key')->from('ticket_statuses')->where('is_open', true))
            ->whereHas('roleAssignments', fn ($q) => $q->where('user_id', $user->id))
            ->orderBy('id')
            ->pluck('ticket_number');
    }

    /**
     * Titles of unfinished subtasks still owned by this person.
     *
     * @return \Illuminate\Support\Collection<int, string>
     */
    public function openSubtasks(User $user): \Illuminate\Support\Collection
    {
        return TicketSubtask::query()
            ->where('assignee_id', $user->id)
            ->where('status', '!=', 'done')
            ->orderBy('id')
            ->pluck('title');
    }
}
