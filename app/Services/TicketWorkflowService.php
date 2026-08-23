<?php

namespace App\Services;

use App\Casts\TicketStatusValue;
use App\Models\Role;
use App\Models\Ticket;
use App\Models\TicketRoleAssignment;
use App\Models\TicketStatusDefinition;
use App\Models\TicketSubtask;
use App\Models\TicketWorkLog;
use App\Models\User;
use App\Models\WorklogCompletionWaiver;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * The state machine (PLAN.md § 3, F06, F07).
 *
 * Two rules make this different from a plain status field:
 *   - in_progress and dev_done are never set by hand. They are computed from
 *     ticket_work_logs, because the truth is "did each side actually start /
 *     finish", not "did someone remember to move a dropdown".
 *   - A side that has subtasks can't be called done until its subtasks are —
 *     finishBlocker() per side, subtaskBlocker() for the ticket as a whole.
 */
class TicketWorkflowService
{
    /**
     * Statuses that assert the work is over. Entering either with unfinished
     * subtasks is what let points be awarded for work nobody did.
     */
    private const SUBTASK_GATED = ['resolved', 'closed'];

    /**
     * Statuses that are computed from ticket_work_logs, never chosen by hand
     * (see the class docblock). start() and finish() own them, driven by the
     * بدأت / خلصت buttons.
     *
     * Offering these in a status picker desynchronises the two models: the
     * ticket claims "جاري العمل" while both work logs still say pending, the
     * button still reads بدأت, and allSidesDone() — which the point engine
     * depends on — is reading a different truth than the badge.
     */
    public const COMPUTED_STATUSES = ['in_progress', 'dev_done', 'testing'];

    public function __construct(
        private readonly PointEngineService $points,
        private readonly NotificationService $notifications,
        private readonly SubtaskService $subtasks,
        private readonly DiscordNotificationService $discord,
    ) {
    }

    /**
     * Records the move and writes history. Refuses anything the machine doesn't
     * allow — loudly, never silently (F06).
     *
     * @param  array{type: string, user_id?: int|null, contact_id?: int|null}|null  $recipient
     *         Who the ticket is now waiting on (F06 manual status changes only —
     *         automatic transitions never pass this).
     */
    public function transition(Ticket $ticket, TicketStatusValue $to, ?int $userId = null, ?string $note = null, ?array $recipient = null): Ticket
    {
        $from = $ticket->status;

        if ($from === $to) {
            return $ticket;
        }

        // F15 invariant (2026-07-21): while a feature/module is awaiting its
        // approval decision, its status is frozen. Nothing moves it but the
        // decision itself — approve() sets approval_status without transitioning,
        // and reject() sets it to 'rejected' BEFORE calling us, so both read a
        // non-'pending' status here and pass. Any other caller (the manual panel,
        // a board move, future code) is refused, so an unapproved ticket can
        // never be flipped out of the approvals queue into an un-assignable limbo.
        if ($ticket->type->needsApproval() && $ticket->approval_status === 'pending') {
            throw new DomainException(
                'التذكرة لسه مستنية موافقة — لازم تتوافق أو تترفض الأول قبل ما تتغير حالتها.'
            );
        }

        if (! in_array($to->value, TicketStatusDefinition::transitionMap()[$from->value] ?? [], true)) {
            throw new DomainException(
                "مينفعش تنقل التذكرة من «{$from->label()}» لـ «{$to->label()}»."
            );
        }

        if (($blocker = $this->subtaskBlocker($ticket, $to)) !== null) {
            throw new DomainException($blocker);
        }

        if (($blocker = $this->unfinishedSideBlocker($ticket, $to)) !== null) {
            throw new DomainException($blocker);
        }

        return DB::transaction(function () use ($ticket, $from, $to, $userId, $note, $recipient) {
            // Before the status is written and before award() runs: a waived
            // person's open subtasks are closed now, so the point engine sees
            // finished work and pays for it. See closeWaivedSubtasks().
            $waivedClosed = in_array($to->value, self::SUBTASK_GATED, true)
                ? $this->closeWaivedSubtasks($ticket)
                : 0;

            if ($waivedClosed > 0) {
                $note = trim(($note ? $note . ' — ' : '')
                    . "اتقفل {$waivedClosed} صب تاسك تلقائياً (إعفاء من «خلصت»)");
            }

            $ticket->status = $to;

            if ($to === TicketStatusValue::for('resolved') && $ticket->resolved_at === null) {
                $ticket->resolved_at = now();
            }

            if ($to === TicketStatusValue::for('closed')) {
                $ticket->closed_at = now();
            }

            $ticket->save();

            $ticket->statusHistory()->create([
                'from_status' => $from->value,
                'to_status' => $to->value,
                'user_id' => $userId,
                'note' => $note,
                'recipient_type' => $recipient['type'] ?? null,
                'recipient_user_id' => $recipient['user_id'] ?? null,
                'recipient_contact_id' => $recipient['contact_id'] ?? null,
            ]);

            // Points are awarded on the first entry into resolved, once, ever. F18
            if ($to === TicketStatusValue::for('resolved')) {
                $this->points->award($ticket);
            }

            $this->announce($ticket, $from, $to, $userId, $note);

            return $ticket;
        });
    }

    /**
     * F06: the manual "غيّر الحالة" action — a status move the user picks by
     * hand, optionally naming who it's waiting on. Naming a recipient also
     * drops a follow-up subtask on the ticket's own developer(s), so the
     * question doesn't just sit on a badge — it shows up on their calendar.
     *
     * @param  array{type: string, user_id?: int|null, contact_id?: int|null}|null  $recipient
     */
    public function changeStatus(Ticket $ticket, TicketStatusValue $to, int $actorId, ?string $note, ?array $recipient): Ticket
    {
        return DB::transaction(function () use ($ticket, $to, $actorId, $note, $recipient) {
            $ticket = $this->transition($ticket, $to, $actorId, $note, $recipient);

            if ($recipient !== null) {
                $this->createFollowUpSubtask($ticket, $to, $note, $actorId);
            }

            return $ticket;
        });
    }

    /**
     * One subtask per work-logging role the ticket is actually assigned to — the
     * question is the ticket's own developer's to chase, never the recipient's,
     * regardless of whether the recipient is a colleague or a client contact.
     *
     * Role-based since the fixed columns were dropped (2026-07-24): "the
     * developers" is now "whoever holds a logs_work role", so a custom role an
     * admin flagged as working the ticket gets a follow-up too.
     */
    private function createFollowUpSubtask(Ticket $ticket, TicketStatusValue $to, ?string $note, int $actorId): void
    {
        $title = "متابعة: {$ticket->title} — {$to->label()}";

        $workLoggingRoleIds = Role::workLoggingRoleIds();

        $assignments = $ticket->roleAssignments()
            ->whereIn('role_id', $workLoggingRoleIds)
            ->get(['role_id', 'user_id']);

        foreach ($assignments as $assignment) {
            $this->subtasks->create($ticket, [
                'title' => $title,
                'description' => $note,
                'assignee_id' => $assignment->user_id,
                'role_id' => $assignment->role_id,
                'due_date' => now()->toDateString(),
            ], $actorId);

            $this->notifications->notifyUser(
                $assignment->user_id,
                $ticket,
                'subtask.assigned',
                "اتعملك صب تاسك متابعة على {$ticket->ticket_number}: {$to->label()}",
                $actorId,
            );
        }
    }

    /** F20: the events worth interrupting someone for — and only those. */
    private function announce(Ticket $ticket, TicketStatusValue $from, TicketStatusValue $to, ?int $actorId, ?string $note): void
    {
        // Discord hears about every move, including the reopened case the bell
        // treats specially below. It applies its own gates: a ticket still
        // awaiting approval, or one with no announcement yet, is silent.
        $this->discord->statusChanged($ticket, $from, $to, $actorId, $note);

        // A bounced ticket is the one event a developer must not miss. F16
        if ($to === TicketStatusValue::for('reopened')) {
            // Every work-logging role's holder — the role-based "the developers".
            $devIds = $ticket->roleAssignments()
                ->whereIn('role_id', Role::workLoggingRoleIds())
                ->pluck('user_id');

            foreach ($devIds as $devId) {
                $this->notifications->notifyUser(
                    $devId,
                    $ticket,
                    'ticket.reopened',
                    "التيستر رجّع {$ticket->ticket_number}: " . ($note ?? 'من غير سبب'),
                    $actorId,
                );
            }

            return;
        }

        if ($to === TicketStatusValue::for('pending_approval')) {
            return;
        }

        $this->notifications->notifyWatchers(
            $ticket,
            'ticket.status_changed',
            "{$ticket->ticket_number} بقت «{$to->label()}»",
            $actorId,
        );
    }

    /**
     * Distribution is fully role-based (2026-07-24). Every assignment — the two
     * developers, the tester, devops, and any custom role an admin opted in — is
     * a ticket_role_assignments row. Two role flags drive the behaviour the four
     * fixed columns used to hardcode:
     *
     *   - logs_work: the assignment gets a بدأت/خلصت work log (F07), and a
     *     starter subtask so completing it earns points (F18).
     *   - is_tester: the ticket enters the testing queue once development is
     *     done (F16), handled in finish().
     *
     * @param  array<int, int|null>  $roleAssignments  role_id => user_id (null = unassign)
     * @param  bool  $activation  true when this is the ticket's first distribution
     *         — approval, creation, or the exception intake. Discord uses it to
     *         key the initial DMs so a repeated approval cannot send them twice.
     *         Passed explicitly rather than inferred: a ticket that predates the
     *         Discord integration also looks "not yet announced", and guessing
     *         from that would wrongly de-duplicate a real hand-off on it.
     * @param  string|null  $source  who caused this. 'subtask' means a subtask
     *         hand-off dragged the distribution with it — see
     *         DiscordNotificationService::SOURCE_SUBTASK. It suppresses the
     *         generic ticket-level Discord messages ONLY, so the same person is
     *         not DMed twice about one click; the work log, the bell and the
     *         activity log are untouched.
     */
    public function assign(Ticket $ticket, array $roleAssignments, int $actorId, bool $activation = false, ?string $source = null): Ticket
    {
        // A feature can't be worked on before it's approved — the Policy blocks
        // the button, and this blocks everything else. F15
        if ($ticket->type->needsApproval() && $ticket->approval_status !== 'approved') {
            throw new DomainException('الفيتشر لازم توافق عليه الأول قبل ما يتوزع.');
        }

        return DB::transaction(function () use ($ticket, $roleAssignments, $actorId, $activation, $source) {
            $this->assignRoles($ticket, $roleAssignments, $actorId, $activation, $source);

            if ($ticket->status === TicketStatusValue::for('new') || $ticket->status === TicketStatusValue::for('pending_approval')) {
                $this->transition($ticket, TicketStatusValue::for('assigned'), $actorId, 'تم التوزيع');
            }

            return $ticket->refresh();
        });
    }

    /**
     * The one assignment path (F06). For every role_id => user_id: assign or
     * unassign, notify only a newly-assigned person, and — for a logs_work role
     * — keep its work log and starter subtask in sync. A starter subtask on the
     * first assignment means completing the work earns points the same way for
     * every role (F18), which is exactly what made the old devops participation
     * special-case unnecessary.
     *
     * ★ (2026-08-02) The global $seedStarters switch is gone. It was an
     * all-or-nothing kill: the moment the creator hand-wrote ONE subtask, the
     * starter was suppressed for EVERY role — so a ticket with a hand-written
     * frontend plan left its support and devops assignees with no subtask at
     * all, and F18 pays per subtask, so they earned nothing. TK-2026-00169 lost
     * 7 subtasks' worth of points exactly this way.
     *
     * The per-role guard below (`! ...->where('role_id', $roleId)->exists()`)
     * was always the real rule and is enough on its own: a role the creator
     * already planned for keeps their plan, every other assigned role still
     * gets its starter.
     *
     * @param  array<int, int|null>  $roleAssignments  role_id => user_id
     */
    private function assignRoles(Ticket $ticket, array $roleAssignments, int $actorId, bool $activation = false, ?string $source = null): void
    {
        if ($roleAssignments === []) {
            return;
        }

        // Collected rather than announced inline: Discord wants one call with
        // every role that moved, so a three-role hand-off is one pass instead of
        // three, and the loop below keeps reading as the assignment logic it is.
        $diffs = [];

        // ★ (2026-08-23) Same lock, same order, as SubtaskAssignmentService.
        //
        // Distribution can now be changed from two directions — this panel, and
        // a subtask hand-off that drags the role with it — and they can collide:
        // one admin moves the role to Ahmed while another opens a subtask for
        // Omar. Each checks, each sees nothing in its way, and both commit,
        // leaving the ticket naming one person and the only open subtask
        // another.
        //
        // Both paths take THIS row first, before touching anything else, so they
        // serialise instead of deadlocking. The reads below take their own lock
        // for the same reason SubtaskAssignmentService's do: under REPEATABLE
        // READ a plain SELECT answers from this transaction's snapshot and would
        // not see the other admin's freshly committed work at all.
        Ticket::whereKey($ticket->id)->lockForUpdate()->first();

        $roles = Role::query()->assignableOnTickets()->whereIn('id', array_keys($roleAssignments))->get()->keyBy('id');
        $existing = $ticket->roleAssignments()->lockForUpdate()->get()->keyBy('role_id');

        // Everything is checked before anything is written, so a refusal leaves
        // no half-applied distribution behind.
        $this->assertNoOpenSubtaskConflict($ticket, $roleAssignments, $roles, $existing);

        foreach ($roleAssignments as $roleId => $userId) {
            // ★ (2026-08-23) Normalised before anything compares it.
            //
            // The form posts "4", not 4 — 'integer' in AssignTicketRequest is a
            // validation rule, not a cast, so validated() hands back the string.
            // The no-op guard below is a strict ===, so against an int column it
            // was never true over HTTP: re-saving the distribution unchanged
            // rewrote every row and re-notified every assignee, every time.
            // Invisible while the only consequence was a duplicate bell; not
            // invisible once it means a Discord DM saying the ticket moved when
            // it did not.
            $userId = ($userId === null || $userId === '') ? null : (int) $userId;

            $role = $roles->get($roleId);

            // Ignore an id that isn't (or is no longer) opted into assignment —
            // never silently assign a role nobody enabled here.
            if ($role === null) {
                continue;
            }

            $before = $existing->get($roleId);

            if ($userId === null) {
                if ($before !== null) {
                    $diffs[] = [
                        'role_id' => $roleId,
                        'role_name' => $role->name_ar,
                        'role_key' => $role->key,
                        'from' => $before->user_id,
                        'to' => null,
                    ];
                }

                // The starter follows the role into being unowned too, for the
                // same reason it follows a hand-off: it is the role's
                // placeholder, and a placeholder pointing at somebody the ticket
                // no longer lists is the inconsistency this all exists to stop.
                // It is emptied rather than deleted or completed — deleting
                // would destroy a row F18 may already have paid on, and
                // completing it would claim work finished that nobody did.
                $ticket->subtasks()
                    ->where('role_id', $roleId)
                    ->generatedStarter()
                    ->where('status', '!=', 'done')
                    ->update(['assignee_id' => null]);

                $before?->delete();

                // Un-assigning a work-logging role drops its commitment, but only
                // while nothing has been done on it — otherwise history is lost.
                if ($role->logsWork()) {
                    $ticket->workLogs()
                        ->where('role_id', $roleId)
                        ->where('status', 'pending')
                        ->delete();
                }

                continue;
            }

            if ($before !== null && $before->user_id === $userId) {
                continue;
            }

            TicketRoleAssignment::updateOrCreate(
                ['ticket_id' => $ticket->id, 'role_id' => $roleId],
                ['user_id' => $userId]
            );

            // Reached only for a real change — the guard above returns early when
            // the same person is saved again, which is why re-submitting the
            // distribution form unchanged tells Discord nothing.
            $diffs[] = [
                'role_id' => $roleId,
                'role_name' => $role->name_ar,
                'role_key' => $role->key,
                'from' => $before?->user_id,
                'to' => $userId,
            ];

            $this->notifications->notifyUser(
                $userId,
                $ticket,
                'ticket.assigned',
                "اتعملك أساين على {$ticket->ticket_number} كـ{$role->name_ar}: {$ticket->title}",
                $actorId,
            );

            // A work-logging role gets the بدأت/خلصت commitment the ticket's
            // status machine acts on (F07). Re-assigning to a new person keeps
            // the existing log and just moves its owner.
            if ($role->logsWork()) {
                TicketWorkLog::updateOrCreate(
                    ['ticket_id' => $ticket->id, 'role_id' => $roleId],
                    ['user_id' => $userId]
                );
            }

            // First assignment for this role only — a hand-off to someone else
            // never repeats it (F06.3). The starter means a newly-assigned role
            // is never an empty list, and gives F18 something to pay. Skipped
            // only for a role the creator already wrote a subtask for.
            // ★ The generated starter follows the role it belongs to.
            //
            // It is the distribution's own placeholder, so leaving it with the
            // previous holder would be the very inconsistency the conflict check
            // above exists to prevent — and it is why the starter is exempt from
            // that check rather than simply ignored. Same transaction as the
            // assignment write, so the two can never disagree.
            //
            // Deliberately silent: this is internal bookkeeping, and the ticket
            // hand-off already tells both people what happened. Firing subtask
            // DMs here would send everybody a second message about one click.
            // Finished starters are history and stay where they are.
            $ticket->subtasks()
                ->where('role_id', $roleId)
                ->generatedStarter()
                ->where('status', '!=', 'done')
                ->update(['assignee_id' => $userId]);

            if ($before === null && ! $ticket->subtasks()->where('role_id', $roleId)->exists()) {
                $this->subtasks->create($ticket, [
                    'title' => $ticket->title,
                    'assignee_id' => $userId,
                    'role_id' => $roleId,
                    // Stamped so the distribution rules can tell this placeholder
                    // from work a person assigned — it must follow the role
                    // holder rather than block them. See TicketSubtask::ORIGIN_*.
                    'origin' => TicketSubtask::ORIGIN_DISTRIBUTION_STARTER,
                ], $actorId);
            }
        }

        $this->discord->assignmentsChanged($ticket, $diffs, $actorId, $activation, $source);
    }

    /**
     * Refuses a distribution change that would strand an unfinished subtask.
     *
     * ★ (2026-08-23) The other half of the rule SubtaskAssignmentService
     * enforces. That one keeps the ticket in step when a SUBTASK moves; this one
     * stops the ticket moving out from under a subtask that is still open.
     * Without it the invariant only held in one direction: handing the back-end
     * role to somebody else left «Fix API» sitting with the previous owner while
     * the ticket said the work belonged to the new one, and the finish gate,
     * the board and the points ledger disagreed about which was true.
     *
     * Silently reassigning those subtasks would move work nobody asked us to
     * touch; silently closing them would pay out or void points on somebody's
     * behalf. Naming them and stopping is the only option that does not decide
     * something for the admin.
     *
     * Finished subtasks are history and never block. Unassigning a role (null)
     * does not block either — that mirrors the subtask side, which deliberately
     * does not strip a ticket role when a step is parked.
     *
     * The subtask-driven path reaches this too and passes cleanly: by the time
     * SubtaskAssignmentService calls assign(), the subtask in question already
     * belongs to the incoming user, and any rival was refused before that.
     *
     * @param  array<int, int|null>  $roleAssignments
     * @param  \Illuminate\Support\Collection<int, Role>  $roles
     * @param  \Illuminate\Support\Collection<int, TicketRoleAssignment>  $existing
     *
     * @throws DomainException
     */
    private function assertNoOpenSubtaskConflict(Ticket $ticket, array $roleAssignments, $roles, $existing): void
    {
        $problems = [];

        foreach ($roleAssignments as $roleId => $userId) {
            $userId = ($userId === null || $userId === '') ? null : (int) $userId;
            $role = $roles->get($roleId);

            if ($role === null) {
                continue;
            }

            $before = $existing->get($roleId);

            // The same three guards the write loop applies, in the same order,
            // so this can never refuse something the loop would have skipped —
            // re-saving a distribution unchanged stays a true no-op.
            if ($before !== null && $before->user_id === $userId) {
                continue;
            }

            if ($userId === null && $before === null) {
                continue;
            }

            $stranded = $ticket->subtasks()
                ->where('role_id', $roleId)
                ->where('status', '!=', 'done')
                ->whereNotNull('assignee_id')
                // ★ The generated starter is deliberately NOT a blocker. It is
                // the role's own placeholder, not work somebody chose to hand
                // out, and it follows the holder a few lines further down. Only
                // real work can strand.
                ->realWork()
                // Unassigning strands EVERY open owner, not just a different
                // one — leaving the role empty while somebody is still on the
                // work is the same inconsistency by another route.
                ->when($userId !== null, fn ($q) => $q->where('assignee_id', '!=', $userId))
                ->lockForUpdate()
                ->get(['id', 'title', 'assignee_id']);

            if ($stranded->isEmpty()) {
                continue;
            }

            $owners = User::whereIn('id', $stranded->pluck('assignee_id')->unique())->pluck('name', 'id');

            // Every conflicting subtask is listed, not just the first: being
            // told about them one at a time is the slowest way to clear a role.
            $list = $stranded
                ->map(fn ($t) => "«{$t->title}» مع " . ($owners[$t->assignee_id] ?? "#{$t->assignee_id}"))
                ->implode('، ');

            $problems[] = $userId === null
                ? "مينفعش تشيل التوزيع عن دور «{$role->name_ar}» لأن فيه صب تاسك لسه مفتوحة: {$list}."
                : "مينفعش تغيّر توزيع دور «{$role->name_ar}» لـ"
                    . (User::whereKey($userId)->value('name') ?? "#{$userId}")
                    . " لأن فيه صب تاسك لسه مفتوحة ومع حد تاني: {$list}.";
        }

        if ($problems !== []) {
            throw new DomainException(implode(' ', $problems) . ' سلّمها أو قفلها الأول.');
        }
    }

    /**
     * F15: save the distribution the user picked at creation for a ticket that
     * still needs approval. The choice is persisted as ticket_role_assignments
     * rows so it isn't lost, but NOTHING acts on it yet — no starter subtask, no
     * work log, no notification, and the status stays pending_approval. approve()
     * activates these rows the moment the ticket is approved.
     *
     * @param  array<int, int|null>  $roleAssignments  role_id => user_id
     */
    public function planAssignments(Ticket $ticket, array $roleAssignments, int $actorId): void
    {
        $roleAssignments = array_filter($roleAssignments, fn ($v) => filled($v));

        if ($roleAssignments === []) {
            return;
        }

        $assignable = Role::query()->assignableOnTickets()
            ->whereIn('id', array_keys($roleAssignments))
            ->pluck('id');

        foreach ($roleAssignments as $roleId => $userId) {
            // Never plan a role nobody opted into assignment — same guard the
            // live path uses, so a stale id can't sneak in as a plan row.
            if (! $assignable->contains($roleId)) {
                continue;
            }

            TicketRoleAssignment::updateOrCreate(
                ['ticket_id' => $ticket->id, 'role_id' => $roleId],
                ['user_id' => $userId]
            );
        }
    }

    /** "بدأت" — the first side to start drags the ticket to in_progress. F07 */
    public function start(TicketWorkLog $log, int $actorId): void
    {
        if ($log->status !== 'pending') {
            throw new DomainException('الشغل ده مبدوء بالفعل.');
        }

        DB::transaction(function () use ($log, $actorId) {
            $log->update(['status' => 'in_progress', 'started_at' => now()]);

            $ticket = $log->ticket;

            if (in_array($ticket->status, [TicketStatusValue::for('assigned'), TicketStatusValue::for('reopened')], true)) {
                $this->transition($ticket, TicketStatusValue::for('in_progress'), $actorId, "{$log->roleLabel()}: بدأ الشغل");
            }
        });
    }

    /**
     * "خلصت" — only moves the ticket to dev_done when EVERY side is done. If the
     * frontend finishes and the backend hasn't, the ticket stays in_progress. F07
     */
    public function finish(TicketWorkLog $log, int $actorId): void
    {
        if ($log->status !== 'in_progress') {
            throw new DomainException('لازم تضغط «بدأت» الأول.');
        }

        if (($blocker = $this->finishBlocker($log)) !== null) {
            throw new DomainException($blocker);
        }

        DB::transaction(function () use ($log, $actorId) {
            // The log is in_progress, so the ticket is too — reconcile if a
            // manual status move or a reassignment left it behind at «موزعة»
            // (or «مرتجعة») while work was actually running. Without this, the
            // dev_done cascade below has no valid path (assigned → dev_done
            // doesn't exist) and «خلصت» throws «مينفعش تنقل … لـ تم التطوير»,
            // stranding the ticket. Bringing it to in_progress first — the state
            // its own work log already implies — un-sticks it and heals the
            // desync (2026-07-21). start() does the same for the pending → begin
            // direction; this is its finish-side twin.
            $ticket = $log->ticket;
            if (in_array($ticket->status, [TicketStatusValue::for('assigned'), TicketStatusValue::for('reopened')], true)) {
                $this->transition($ticket, TicketStatusValue::for('in_progress'), $actorId, "{$log->roleLabel()}: استئناف الشغل");
            }

            $finishedAt = now();

            $log->update([
                'status' => 'done',
                'finished_at' => $finishedAt,
                'duration_minutes' => $log->started_at
                    ? (int) $log->started_at->diffInMinutes($finishedAt)
                    : null,
            ]);

            $ticket = $log->ticket->refresh();

            $this->promoteIfSidesDone($ticket, $actorId, 'كل الجهات خلصت');
        });
    }

    /**
     * ★ The rule that links the two layers: a side with subtasks can't be
     * finished until they're all done (F07). Subtasks arrive in phase 4; until
     * the table exists there is nothing to block on.
     */
    public function finishBlocker(TicketWorkLog $log): ?string
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('ticket_subtasks')) {
            return null;
        }

        // A work log's subtasks are the ones tagged to its role (F07). Role-based
        // since the columns were dropped: the starter and any follow-ups carry
        // role_id, so the gate reads role_id rather than the old WorkSide.
        $open = DB::table('ticket_subtasks')
            ->where('ticket_id', $log->ticket_id)
            ->where('role_id', $log->role_id)
            ->whereNull('deleted_at')
            ->where('status', '!=', 'done')
            ->count();

        return $open === 0
            ? null
            : "لسه فيه {$open} صب تاسك مش خالصة على جهة {$log->roleLabel()}. خلّصها الأول.";
    }

    /**
     * ★ The same rule, one level up: the whole ticket can't claim the work is
     * over while any subtask is still open.
     *
     * finishBlocker() guards one work log's «خلصت», per side. That left a hole:
     * transitionMap seeds assigned→resolved and new→resolved, so the "غيّر
     * الحالة" panel could jump straight to resolved with subtasks still open —
     * and award() fires on entry to resolved. Points become money in bonuses,
     * so this is guarded at transition(), the single choke point every status
     * change passes through: the panel, the board drag, and close() all inherit
     * it from here.
     *
     * closed is gated as well as resolved. Today closed is only reachable from
     * resolved, so gating resolved covers it — but the graph is admin-editable
     * at /admin/ticket-statuses, and the day someone adds dev_done→closed that
     * transitive protection disappears silently. Gating both costs nothing.
     *
     * Sides are deliberately NOT filtered. SubtaskSide::blocksWorkLog() answers
     * "does this block THIS side's خلصت"; at ticket level the question is "is
     * the work done", and a qa or support subtask is work.
     */
    /**
     * F07 at the transition choke point (2026-07-21): a ticket a developer is
     * still working can't be forced to a final stage. finish()'s allSidesDone
     * already gates the «خلصت» button, but the manual «غيّر الحالة» panel and a
     * board drop reach resolved/closed through transition() directly, and
     * subtaskBlocker only guards OPEN SUBTASKS — which are optional, so a ticket
     * with none had no work-side gate at all and could be resolved mid-work.
     * That entered resolved and fired award() on unfinished work — money. This
     * closes it: every work log that exists must be 'done'. A ticket with no
     * work logs (support-only, never assigned to a dev) has nothing to finish
     * and passes untouched, so support tickets still resolve normally.
     */
    private function unfinishedSideBlocker(Ticket $ticket, TicketStatusValue $to): ?string
    {
        if (! in_array($to->value, self::SUBTASK_GATED, true)) {
            return null;
        }

        // Only a role the ticket is STILL assigned to can block it. When a
        // work-logging role is un-assigned (or handed to a different role), its
        // work log is kept for history but is no longer this ticket's commitment
        // and must not block the resolve forever. Without this, a reassigned
        // ticket got stuck on an orphaned role that nobody is working any more
        // (2026-07-21, role-based since 2026-07-24).
        $assignedRoleIds = $ticket->roleAssignments()->pluck('role_id')->all();
        $partners = $this->subtaskAssigneeIds($ticket);

        $unfinished = $ticket->workLogs()
            ->where('status', '!=', 'done')
            ->whereIn('role_id', $assignedRoleIds)
            ->with('role:id,name_ar')
            ->get()
            ->reject(fn (TicketWorkLog $log) => $this->completionWaived($log, $partners));

        if ($unfinished->isEmpty()) {
            return null;
        }

        $sides = $unfinished->map(fn ($log) => $log->roleLabel())->implode(' و');

        return "لسه فيه شغل مخلّصش على التذكرة ({$sides}). لازم كل جهة تضغط «خلصت» قبل ما تحوّلها لـ «{$to->label()}».";
    }

    private function subtaskBlocker(Ticket $ticket, TicketStatusValue $to): ?string
    {
        if (! in_array($to->value, self::SUBTASK_GATED, true)) {
            return null;
        }

        // SubtaskService maintains these counters on every mutation (§ 4.6), so
        // the passing case — the overwhelming majority — is arithmetic on a row
        // already in memory. Zero queries.
        if ($ticket->subtasks_total - $ticket->subtasks_done <= 0) {
            return null;
        }

        // Only a suspected block pays for an authoritative count. reorder()
        // drops the relation's default orderBy('position'), which
        // ONLY_FULL_GROUP_BY rejects on an aggregate — same reason as
        // SubtaskService::syncCounters().
        //
        // ★ (2026-08-02) A subtask on a non-work-logging role (دعم, ديف أوبس)
        // no longer blocks. Those roles get a starter subtask so their work is
        // tracked and paid, but they are support around the delivery, not the
        // delivery — a ticket the developers finished shouldn't sit open on
        // them. This makes the two gates agree: unfinishedSideBlocker already
        // only counts logs_work roles. A subtask with no role at all still
        // blocks — that's general work nobody has claimed.
        // ★ (2026-08-05) A waived person's subtasks do not gate either.
        //
        // The waiver used to cover only the «خلصت» work log, which turned out to
        // be the half nobody was blocked by: a ticket with 20 of 22 subtasks done
        // sat open on the two that were not, and the waiver had nothing to say
        // about them. Both gates now read the same rule, which is what "he does
        // not have to mark it finished for me to move it" meant all along.
        $open = $ticket->subtasks()->reorder()
            ->where('status', '!=', 'done')
            ->where(fn ($q) => $q->whereNull('role_id')
                ->orWhereIn('role_id', Role::workLoggingRoleIds()))
            ->whereNotIn('assignee_id', $this->waivedAssigneeIds($ticket))
            ->count();

        if ($open === 0) {
            // Either the counter drifted, or everything still open belongs to a
            // role that doesn't gate. Re-sync so the drift case self-heals; the
            // non-gating case is simply not a block.
            $this->subtasks->syncCounters($ticket);

            return null;
        }

        return "لسه فيه {$open} صب تاسك مش خالصة على التذكرة. خلّصها أو احذفها قبل ما تحوّلها لـ «{$to->label()}».";
    }

    /**
     * The inverse of finish(): a side that said "خلصت" takes it back.
     *
     * Same shape as the reopen action a tester performs, minus the customer
     * framing — this is the developer correcting themselves, not the ticket
     * being returned. The recorded time is kept: the work really did happen,
     * and duration_minutes feeds reports that must not lose it.
     */
    public function resume(TicketWorkLog $log, int $actorId): void
    {
        if ($log->status !== 'done') {
            throw new DomainException('الشغل ده مش مقفول أصلاً.');
        }

        DB::transaction(function () use ($log, $actorId) {
            $log->update(['status' => 'in_progress', 'finished_at' => null]);

            $ticket = $log->ticket->refresh();

            // dev_done and testing both mean "development is over". Taking a
            // side back makes that untrue, so the ticket follows its sides.
            if (in_array($ticket->status->value, ['dev_done', 'testing'], true)) {
                $this->transition($ticket, TicketStatusValue::for('in_progress'), $actorId, "{$log->roleLabel()}: رجع يشتغل");
            }
        });
    }

    /**
     * The inverse of start(): a side that said "بدأت" puts it back down.
     *
     * started_at is cleared because the side genuinely has not started — and
     * leaving it set would make the next finish() compute duration_minutes from
     * a timestamp that no longer means anything. Hours actually worked live in
     * time_entries, which this never touches.
     */
    public function unstart(TicketWorkLog $log, int $actorId): void
    {
        if ($log->status !== 'in_progress') {
            throw new DomainException('الشغل ده مش شغّال دلوقتي.');
        }

        DB::transaction(function () use ($log, $actorId) {
            $log->update(['status' => 'pending', 'started_at' => null]);

            $ticket = $log->ticket->refresh();

            // in_progress means "somebody is on it". Once nobody is, the ticket
            // has to say so, or the card keeps showing خلصت for work that was
            // never started.
            if ($ticket->status === TicketStatusValue::for('in_progress')
                && $ticket->workLogs()->where('status', '!=', 'pending')->doesntExist()) {
                $this->transition($ticket, TicketStatusValue::for('assigned'), $actorId, 'رجعت لقائمة الانتظار');
            }
        });
    }

    private function allSidesDone(Ticket $ticket): bool
    {
        $logs = $ticket->workLogs()->get(['id', 'ticket_id', 'user_id', 'status']);
        $partners = $this->subtaskAssigneeIds($ticket);

        // isNotEmpty() on ALL the logs, every() on the binding ones only. The
        // first still means what it always meant — a ticket nobody was ever
        // assigned to has not "finished" — while the second lets a waived
        // person's open log stop holding «تم التطوير» back. With every side
        // waived the move happens on the first «خلصت» anyone presses, which is
        // the only moment this is reached.
        $binding = $logs->reject(fn (TicketWorkLog $log) => $this->completionWaived($log, $partners));

        return $logs->isNotEmpty() && $binding->every(fn ($log) => $log->status === 'done');
    }

    /**
     * Everyone holding a subtask on this ticket. The set a waiver is matched
     * against, read once per transition rather than once per work log.
     *
     * @return array<int, int>
     */
    private function subtaskAssigneeIds(Ticket $ticket): array
    {
        return DB::table('ticket_subtasks')
            ->where('ticket_id', $ticket->id)
            ->whereNull('deleted_at')
            ->whereNotNull('assignee_id')
            ->distinct()
            ->pluck('assignee_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * Is this person let off having to press «خلصت» on THIS ticket?
     *
     * ★ (2026-08-05) A waiver is a pair, not a flag. "Ahmed does not have to
     * finish" was too blunt — it stopped his «خلصت» meaning anything anywhere.
     * What was wanted is "…on the tickets he shares with Mahmoud", so the row
     * names both people and this checks whether the counterpart is actually on
     * the ticket in front of us.
     *
     * "Shares a ticket" is deliberately read as SUBTASKS, not assignments: a
     * colleague who is assigned but has no subtask has no work here to wait on.
     * That is a narrower rule than it first sounds — a waiver simply does not
     * apply on a ticket where the counterpart holds nothing.
     *
     * A null counterpart in the table is the wildcard: waived with everyone.
     *
     * @param  array<int, int>  $subtaskAssigneeIds  who holds a subtask on this ticket
     */
    /**
     * Moves the ticket on if every side that still has to finish, has.
     *
     * ★ (2026-08-05) Split out of finish() so it can be run again later.
     *
     * dev_done is a computed status — it is never in the manual picker, and the
     * only thing that used to compute it was somebody pressing «خلصت». That is
     * fine while the last person to finish is the one who unblocks the ticket,
     * and wrong the moment a waiver is what unblocks it: everyone who was ever
     * going to press the button already had, so nothing re-ran the check and the
     * ticket sat at «جاري العمل» forever. From there the machine offers no route
     * to «تم الحل» at all — in_progress only leads to dev_done — so the ticket
     * was unclosable and the status dropdown looked broken.
     */
    private function promoteIfSidesDone(Ticket $ticket, ?int $actorId, string $note): void
    {
        if (! $this->allSidesDone($ticket)) {
            return;
        }

        $this->transition($ticket, TicketStatusValue::for('dev_done'), $actorId, $note);

        // No tester means nobody is going to verify it, so the ticket waits for
        // support or a manager rather than sitting in limbo. F16 — "has a
        // tester" is "holds an is_tester role assignment".
        $hasTester = $ticket->roleAssignments()
            ->whereIn('role_id', Role::testerRoleIds())
            ->exists();

        if ($hasTester) {
            $this->transition($ticket, TicketStatusValue::for('testing'), $actorId, 'في انتظار التيست');
        }
    }

    /**
     * Re-runs that check for every open ticket this person is holding up.
     *
     * Called after their waivers change: the waiver is what has just made those
     * tickets finishable, and without this the change only takes effect on
     * tickets where somebody presses «خلصت» afterwards — which, on a ticket
     * everyone else has already finished, is nobody.
     *
     * @return int how many tickets moved on
     */
    public function reevaluateFor(int $userId, ?int $actorId = null): int
    {
        $tickets = Ticket::query()
            ->where('status', 'in_progress')
            ->whereHas('workLogs', fn ($q) => $q->where('user_id', $userId)->where('status', '!=', 'done'))
            ->get();

        $moved = 0;

        foreach ($tickets as $ticket) {
            $before = $ticket->status->value;

            try {
                $this->promoteIfSidesDone($ticket, $actorId, 'اتفك البلوك بإعفاء من «خلصت»');
            } catch (DomainException) {
                // One stuck ticket must not stop the rest — a feature awaiting
                // approval, say, refuses to move and that is correct.
                continue;
            }

            if ($ticket->fresh()?->status->value !== $before) {
                $moved++;
            }
        }

        return $moved;
    }

    /**
     * Everyone on this ticket whose obligation is waived here.
     *
     * A waiver is a pair, so "waived" is a question about this ticket: the
     * counterpart has to be holding a subtask on it. Returns the ids of the
     * people whose own subtasks and work log therefore stop gating.
     *
     * Never returns an empty-array-shaped surprise for whereNotIn: an empty
     * list is fine there, it simply excludes nobody.
     *
     * @return array<int, int>
     */
    private function waivedAssigneeIds(Ticket $ticket): array
    {
        return WorklogCompletionWaiver::waivedAmong($this->subtaskAssigneeIds($ticket));
    }

    /**
     * Closes the still-open subtasks of anyone waived on this ticket, at the
     * moment the ticket is resolved or closed.
     *
     * ★ (2026-08-05) This is what makes "she still gets her points" true.
     *
     * PointEngineService pays for subtasks whose status is done and nothing
     * else. Leaving a waived person's subtask sitting at «مستنية» would let the
     * ticket close past them and quietly cost them the points for work the
     * ticket is being closed on the strength of. Worse, LatePenaltyService docks
     * an overdue subtask every morning it is still unfinished — so the row would
     * have gone on bleeding points forever.
     *
     * Closing them here means the money path is untouched: the existing engine
     * sees ordinary finished subtasks and pays them the ordinary way. The late
     * penalty applies exactly as it would if they had pressed done themselves
     * today, which is the deal — the waiver excuses saying "finished", not
     * missing the date.
     *
     * @return int how many were closed, for the status-history note
     */
    private function closeWaivedSubtasks(Ticket $ticket): int
    {
        $waived = $this->waivedAssigneeIds($ticket);

        if ($waived === []) {
            return 0;
        }

        $open = $ticket->subtasks()->reorder()
            ->where('status', '!=', 'done')
            ->whereIn('assignee_id', $waived)
            ->get();

        foreach ($open as $subtask) {
            // Through the service, so completed_at, the counters and every other
            // rule that hangs off "a subtask became done" behave identically to
            // the owner having clicked it.
            $this->subtasks->update($subtask, ['status' => 'done']);
        }

        return $open->count();
    }

    private function completionWaived(TicketWorkLog $log, array $subtaskAssigneeIds): bool
    {
        $waiver = WorklogCompletionWaiver::map()[$log->user_id] ?? null;

        if ($waiver === null) {
            return false;
        }

        if ($waiver['all']) {
            return true;
        }

        return array_intersect($waiver['with'], $subtaskAssigneeIds) !== [];
    }

    /** F15 */
    public function approve(Ticket $ticket, int $adminId): Ticket
    {
        return DB::transaction(function () use ($ticket, $adminId) {
            $from = $ticket->status;

            // Approval also advances the status OUT of pending_approval (2026-07-21).
            // It used to only flip approval_status and leave the status where it was,
            // so an approved-but-unassigned ticket kept the «بانتظار الموافقة» badge —
            // it read as still-pending, was gone from the approvals queue (correctly,
            // it's approved), and wasn't assigned to anyone: an invisible limbo. It
            // lands on 'new' — approved, ready to be assigned, exactly like a
            // non-approval ticket before assignment. assign() then takes new →
            // assigned as usual.
            $advancing = $from->value === 'pending_approval';
            $to = $advancing ? TicketStatusValue::for('new') : $from;

            $ticket->update([
                'approval_status' => 'approved',
                'approved_by' => $adminId,
                'approved_at' => now(),
                'status' => $to,
            ]);

            $ticket->statusHistory()->create([
                'from_status' => $from->value,
                'to_status' => $to->value,
                'user_id' => $adminId,
                'note' => 'تمت الموافقة',
            ]);

            // F15/F06.3: activate the distribution the creator saved (planAssignments).
            // The plan rows carried no side effects while pending; now that work can
            // legally begin, run them through assign() so each role gets its work log,
            // notification, starter subtask and the ticket moves to «موزّعة». Deleting
            // the plan rows first makes assign() see a first assignment for each and
            // apply the full effect.
            //
            // ★ (2026-08-02) The `$seedStarters = ! $ticket->subtasks()->exists()`
            // that used to sit here is gone — it was the exact line that cost
            // TK-2026-00169 its points. One hand-written subtask silenced the
            // starter for every planned role, so roles the creator hadn't
            // planned for arrived with nothing to be paid on. assignRoles()
            // already skips only the roles that HAVE a subtask.
            $planned = $ticket->roleAssignments()->pluck('user_id', 'role_id')->all();

            if ($planned !== []) {
                $ticket->roleAssignments()->delete();
                $this->assign($ticket->refresh(), $planned, $adminId, activation: true);
            }

            $ticket->refresh();

            // ★ Discord goes live here, and last.
            //
            // Everything before this point was planning: the distribution could
            // be typed, retyped and handed between three people while the ticket
            // sat pending, and none of it was real work anybody should have been
            // pinged about. assign() above has just turned the FINAL plan into
            // actual assignments, so each of those people has a first-assignment
            // DM queued — and none of the discarded ones do.
            //
            // The announcement comes after them on purpose. While it does not
            // exist the ticket counts as un-announced, which is what keeps the
            // status moves assign() just made from posting a thread update about
            // a ticket nobody has seen yet. It carries the current distribution
            // in its own embed instead.
            $this->discord->announceCreated($ticket);

            return $ticket;
        });
    }

    /** F15: a rejected ticket earns nobody anything. */
    public function reject(Ticket $ticket, int $adminId, string $reason): Ticket
    {
        return DB::transaction(function () use ($ticket, $adminId, $reason) {
            $ticket->update([
                'approval_status' => 'rejected',
                'approved_by' => $adminId,
                'approved_at' => now(),
            ]);

            return $this->transition($ticket, TicketStatusValue::for('rejected'), $adminId, $reason);
        });
    }

    /** F06: closing requires the customer to have actually been told. */
    public function close(Ticket $ticket, int $actorId): Ticket
    {
        if ($ticket->client_notified_at === null) {
            throw new DomainException('لازم تسجّل إن العميل اتبلغ قبل ما تقفل التذكرة.');
        }

        return $this->transition($ticket, TicketStatusValue::for('closed'), $actorId, 'تم الإغلاق');
    }

    public function markClientNotified(Ticket $ticket, int $actorId): Ticket
    {
        $ticket->update([
            'client_notified_at' => now(),
            'client_notified_by' => $actorId,
        ]);

        return $ticket;
    }
}
