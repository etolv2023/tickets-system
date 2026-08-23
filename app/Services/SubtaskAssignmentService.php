<?php

namespace App\Services;

use App\Models\Role;
use App\Models\Ticket;
use App\Models\TicketSubtask;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Keeps a subtask's owner and the parent ticket's distribution telling the same
 * story.
 *
 * The rule: giving somebody a subtask gives them the ticket role that subtask
 * belongs to. Before this existed the two could disagree — the ticket said the
 * back-end work was Ahmed's while the only back-end subtask sat with Mohamed —
 * and everything downstream believed the ticket: the finish gate (F07) looks at
 * work logs keyed by role, the board columns read the distribution, and the
 * points ledger pays the subtask's assignee. One of those two answers was
 * always wrong.
 *
 * Why a service of its own rather than a hook inside SubtaskService: the
 * distribution creates subtasks too. TicketWorkflowService::assignRoles seeds a
 * starter subtask for each role it hands out, and createFollowUpSubtask does the
 * same for a «مستني رد» step. Those already agree with the distribution by
 * construction — they were built FROM it — and syncing them back would call
 * assign() from inside assign(). So the sync lives on the path a human uses
 * (SubtaskController) and nowhere else.
 *
 * Everything here happens in one transaction. Half of it committing is the exact
 * state this class exists to prevent.
 */
class SubtaskAssignmentService
{
    public function __construct(
        private readonly SubtaskService $subtasks,
        private readonly TicketWorkflowService $workflow,
        private readonly NotificationService $notifications,
        private readonly DiscordNotificationService $discord,
    ) {
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(Ticket $ticket, array $data, int $actorId): TicketSubtask
    {
        $data = $this->normalise($data);

        return DB::transaction(function () use ($ticket, $data, $actorId) {
            // Before the insert, not after — see lockTicket().
            $this->lockTicket($ticket, ($data['assignee_id'] ?? null) !== null);

            $subtask = $this->subtasks->create($ticket, $data, $actorId);

            $synced = $this->syncDistribution($ticket, $subtask, $actorId);

            $this->announce($ticket, $subtask, null, $subtask->assignee_id, $actorId, true, $synced);

            return $subtask;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Ticket $ticket, TicketSubtask $subtask, array $data, int $actorId): TicketSubtask
    {
        $data = $this->normalise($data);

        // Read before anything is written, and as an int — the attribute is an
        // int off the database but a string off the form, and === between those
        // two is how the same person gets told twice that nothing changed.
        $before = $subtask->assignee_id === null ? null : (int) $subtask->assignee_id;

        $fromKey = $subtask->status->value;
        $fromLabel = $subtask->status->label();

        return DB::transaction(function () use ($ticket, $subtask, $data, $actorId, $before, $fromKey, $fromLabel) {
            $incoming = array_key_exists('assignee_id', $data) ? $data['assignee_id'] : $before;

            $this->lockTicket($ticket, $incoming !== $before);

            $this->subtasks->update($subtask, $data);

            // The full form can move the status as well as the owner. They are
            // separate events: one is timeline-only, the other DMs people.
            if ($subtask->refresh()->status->value !== $fromKey) {
                $this->discord->subtaskStatusChanged($ticket, $subtask, $fromKey, $fromLabel, $actorId);
            }

            $after = $subtask->refresh()->assignee_id === null ? null : (int) $subtask->assignee_id;

            if ($after === $before) {
                // A title fix, a due date, a status — real edits, but nobody's
                // work moved, so nothing is announced and the distribution is
                // left exactly as it is.
                return $subtask;
            }

            $synced = $this->syncDistribution($ticket, $subtask, $actorId);

            $this->announce($ticket, $subtask, $before, $after, $actorId, false, $synced);

            return $subtask;
        });
    }

    /**
     * Drags the parent ticket's role onto the subtask's owner.
     *
     * Returns whether it actually changed anything, so the thread post can say
     * so — that is the only place the distribution change is announced, since
     * the ticket layer is deliberately silenced for a subtask-driven move.
     *
     * @throws DomainException when another unfinished subtask holds the same role
     */
    private function syncDistribution(Ticket $ticket, TicketSubtask $subtask, int $actorId): bool
    {
        $roleId = $subtask->role_id;
        $assignee = $subtask->assignee_id === null ? null : (int) $subtask->assignee_id;

        // An unowned step, or one that belongs to no role, carries no claim on
        // the distribution.
        if ($roleId === null || $assignee === null) {
            return false;
        }

        // Clearing a subtask's owner deliberately does NOT clear the ticket role.
        // The role may be held for reasons that have nothing to do with this one
        // step — other subtasks, a work log already in progress — and dropping it
        // because a step was parked would be a destructive guess.

        // A role the admin has not opted into ticket assignment has no slot to
        // sync to. The subtask is still perfectly valid; there is just nothing
        // on the ticket that corresponds to it.
        if (! Role::assignableOnTickets()->whereKey($roleId)->exists()) {
            return false;
        }

        // The ticket row is already locked by the caller (see lockTicket).
        // These reads take their own lock so they answer with the latest
        // COMMITTED state rather than this transaction's snapshot — under
        // REPEATABLE READ a plain SELECT would not see the other admin's
        // just-committed subtask at all, which is the whole failure mode.
        $current = $ticket->roleAssignments()
            ->where('role_id', $roleId)
            ->lockForUpdate()
            ->value('user_id');

        if ($current !== null && (int) $current === $assignee) {
            return false;
        }

        $this->assertNoRivalSubtask($ticket, $subtask, $roleId, $assignee);

        // ★ Through the existing workflow, never by writing the pivot directly.
        // assign() is where the work log is opened, the status is dragged to
        // «موزّعة» and the starter subtask is skipped because this one already
        // covers the role. Writing ticket_role_assignments by hand would produce
        // a ticket that looks distributed and behaves as though it is not.
        if ($ticket->type->needsApproval() && $ticket->approval_status !== 'approved') {
            // The approval boundary holds: assign() refuses an unapproved ticket
            // outright, so the change is recorded as a plan (F15) and activated
            // by approve() along with everything else.
            $this->workflow->planAssignments($ticket, [$roleId => $assignee], $actorId);

            return true;
        }

        $this->workflow->assign(
            $ticket,
            [$roleId => $assignee],
            $actorId,
            source: DiscordNotificationService::SOURCE_SUBTASK,
            // assertNoRivalSubtask ran a few lines up, under the ticket lock we
            // are still holding. Asking again cannot change the answer.
            conflictsChecked: true,
        );

        return true;
    }

    /**
     * Serialises subtask hand-offs on one ticket.
     *
     * Taken FIRST, before any row is written. That ordering is the difference
     * between a clean refusal and a deadlock: when both admins insert their
     * subtask before reaching for the ticket, each ends up holding a row the
     * other needs and InnoDB kills one with a 1213 the admin cannot act on.
     * Locking the ticket up front makes the second request simply wait, and by
     * the time it looks the first has committed — so it gets the business error
     * that names the subtask in its way.
     *
     * Skipped when nobody's ownership is moving, so renaming a step does not
     * queue behind an unrelated reassignment on the same ticket.
     *
     * One row, held to commit. The same thing the exception intake does to stop
     * one error opening two tickets; no lock service, no new infrastructure.
     */
    private function lockTicket(Ticket $ticket, bool $ownershipMoving): void
    {
        if (! $ownershipMoving) {
            return;
        }

        Ticket::whereKey($ticket->id)->lockForUpdate()->first();
    }

    /**
     * Refuses a hand-off that would leave two people owning one role.
     *
     * A ticket holds exactly one person per role — UNIQUE(ticket_id, role_id) —
     * so if another unfinished subtask on this role belongs to somebody else,
     * syncing would strand it: its owner would keep the work while the ticket
     * said the role was someone else's. The alternatives were to reassign that
     * other subtask (silently moving work nobody asked us to touch) or to skip
     * the sync (leaving the inconsistency this class exists to remove). Refusing
     * and naming the obstacle is the only one that does not decide something on
     * the admin's behalf.
     *
     * Finished subtasks are history and never block.
     *
     * @throws DomainException
     */
    private function assertNoRivalSubtask(Ticket $ticket, TicketSubtask $subtask, int $roleId, int $assignee): void
    {
        $rival = $ticket->subtasks()
            ->where('role_id', $roleId)
            ->where('id', '!=', $subtask->id)
            ->where('status', '!=', 'done')
            ->whereNotNull('assignee_id')
            ->where('assignee_id', '!=', $assignee)
            // ★ The generated starter never rivals anything. It is the role's
            // placeholder and it follows whoever ends up holding the role — the
            // sync below drags it along. Counting it here would block the very
            // first manual subtask on any distributed ticket, which is the
            // ordinary case rather than a conflict.
            ->realWork()
            // Locking read: see the note in syncDistribution(). Without it this
            // reads a snapshot that predates the other admin's commit.
            ->lockForUpdate()
            ->first();

        if ($rival === null) {
            return;
        }

        // Resolved separately rather than eager-loaded: the query above is a
        // locking read of one row, and naming the person is a plain lookup.
        $rivalOwner = User::whereKey($rival->assignee_id)->value('name') ?? '#' . $rival->assignee_id;

        throw new DomainException(
            'مينفعش: فيه صب تاسك تانية على نفس الدور لسه مخلصتش ومع حد تاني — '
            . "«{$rival->title}» مع {$rivalOwner}. "
            . 'التذكرة بتشيل شخص واحد بس لكل دور، فسلّم الصب تاسك دي أو قفلها الأول.'
        );
    }

    /**
     * The bell and Discord, for a real change only.
     *
     * The bell notification used to live in SubtaskController behind a
     * string-vs-int comparison that was never false, so an unchanged owner was
     * notified on every save. It moved here so one guard governs both channels
     * and "nothing changed" means nothing at all.
     */
    private function announce(
        Ticket $ticket,
        TicketSubtask $subtask,
        ?int $from,
        ?int $to,
        int $actorId,
        bool $created,
        bool $synced,
    ): void {
        if ($from === $to) {
            return;
        }

        if ($to !== null) {
            $this->notifications->notifyUser(
                $to,
                $ticket,
                'subtask.assigned',
                "اتسندت لك صب تاسك على {$ticket->ticket_number}: {$subtask->title}",
                $actorId,
            );
        }

        $this->discord->subtaskAssignmentChanged($ticket, $subtask, $from, $to, $actorId, $created, $synced);
    }

    /**
     * The form sends "4"; the database holds 4. Everything downstream compares
     * strictly, so the cast happens once, here, at the edge.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalise(array $data): array
    {
        if (array_key_exists('assignee_id', $data)) {
            $data['assignee_id'] = ($data['assignee_id'] === null || $data['assignee_id'] === '')
                ? null
                : (int) $data['assignee_id'];
        }

        if (array_key_exists('role_id', $data)) {
            $data['role_id'] = ($data['role_id'] === null || $data['role_id'] === '')
                ? null
                : (int) $data['role_id'];
        }

        return $data;
    }
}
