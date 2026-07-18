<?php

namespace App\Services;

use App\Casts\TicketStatusValue;
use App\Enums\WorkSide;
use App\Models\Ticket;
use App\Models\TicketStatusDefinition;
use App\Models\TicketWorkLog;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * The state machine (PLAN.md § 3, F06, F07).
 *
 * Two rules make this different from a plain status field:
 *   - in_progress and dev_done are never set by hand. They are computed from
 *     ticket_work_logs, because the truth is "did each side actually start /
 *     finish", not "did someone remember to move a dropdown".
 *   - A side that has subtasks can't be called done until its subtasks are
 *     (enforced in phase 4 via canFinish(); the hook is already here).
 */
class TicketWorkflowService
{
    public function __construct(
        private readonly PointEngineService $points,
        private readonly NotificationService $notifications,
        private readonly SubtaskService $subtasks,
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

        if (! in_array($to->value, TicketStatusDefinition::transitionMap()[$from->value] ?? [], true)) {
            throw new DomainException(
                "مينفعش تنقل التذكرة من «{$from->label()}» لـ «{$to->label()}»."
            );
        }

        return DB::transaction(function () use ($ticket, $from, $to, $userId, $note, $recipient) {
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
     * One subtask per side the ticket is actually assigned to — the question
     * is the ticket's own developer's to chase, never the recipient's,
     * regardless of whether the recipient is a colleague or a client contact.
     */
    private function createFollowUpSubtask(Ticket $ticket, TicketStatusValue $to, ?string $note, int $actorId): void
    {
        $title = "متابعة: {$ticket->title} — {$to->label()}";

        foreach (WorkSide::cases() as $side) {
            $userId = $ticket->{$side->assigneeColumn()};

            if ($userId === null) {
                continue;
            }

            $this->subtasks->create($ticket, [
                'title' => $title,
                'description' => $note,
                'assignee_id' => $userId,
                'side' => $side->value,
                'due_date' => now()->toDateString(),
            ], $actorId);

            $this->notifications->notifyUser(
                $userId,
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
        // A bounced ticket is the one event a developer must not miss. F16
        if ($to === TicketStatusValue::for('reopened')) {
            foreach ([$ticket->assigned_frontend_id, $ticket->assigned_backend_id] as $devId) {
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
     * Assignment creates one work log per side that the scope actually needs.
     * The log is what "start" and "finish" later act on (F06).
     *
     * @param  array{assigned_frontend_id?: int|null, assigned_backend_id?: int|null, tester_id?: int|null}  $assignees
     */
    public function assign(Ticket $ticket, array $assignees, int $actorId): Ticket
    {
        // A feature can't be worked on before it's approved — the Policy blocks
        // the button, and this blocks everything else. F15
        if ($ticket->type->needsApproval() && $ticket->approval_status !== 'approved') {
            throw new DomainException('الفيتشر لازم توافق عليه الأول قبل ما يتوزع.');
        }

        $before = $ticket->only('assigned_frontend_id', 'assigned_backend_id', 'tester_id');

        return DB::transaction(function () use ($ticket, $assignees, $actorId, $before) {
            $ticket->fill($assignees)->save();

            if ($ticket->tester_id !== null && $ticket->tester_id !== $before['tester_id']) {
                $this->notifications->notifyUser(
                    $ticket->tester_id,
                    $ticket,
                    'ticket.assigned',
                    "اتعملك أساين على {$ticket->ticket_number} كتيستر: {$ticket->title}",
                    $actorId,
                );
            }

            foreach (WorkSide::cases() as $side) {
                $userId = $ticket->{$side->assigneeColumn()};

                // Tell the person only when they're newly on it. F20
                if ($userId !== null && $userId !== ($before[$side->assigneeColumn()] ?? null)) {
                    $this->notifications->notifyUser(
                        $userId,
                        $ticket,
                        'ticket.assigned',
                        "اتعملك أساين على {$ticket->ticket_number} كـ{$side->label()}: {$ticket->title}",
                        $actorId,
                    );
                }

                if ($userId === null) {
                    // Un-assigning a side drops its commitment, but only while
                    // nothing has been done on it — otherwise history is lost.
                    $ticket->workLogs()
                        ->where('side', $side->value)
                        ->where('status', 'pending')
                        ->delete();

                    continue;
                }

                TicketWorkLog::updateOrCreate(
                    ['ticket_id' => $ticket->id, 'side' => $side->value],
                    ['user_id' => $userId]
                );

                // A side that's had nobody on it before gets a starter subtask,
                // same title as the ticket, on the same developer — a default
                // starting point, not a requirement (they can retitle/reassign
                // it freely). Reassigning an already-worked side never repeats
                // this, or every hand-off would pile up a fresh duplicate.
                if (($before[$side->assigneeColumn()] ?? null) === null) {
                    $this->subtasks->create($ticket, [
                        'title' => $ticket->title,
                        'assignee_id' => $userId,
                        'side' => $side->value,
                    ], $actorId);
                }
            }

            if ($ticket->status === TicketStatusValue::for('new') || $ticket->status === TicketStatusValue::for('pending_approval')) {
                $this->transition($ticket, TicketStatusValue::for('assigned'), $actorId, 'تم التوزيع');
            }

            return $ticket->refresh();
        });
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
                $this->transition($ticket, TicketStatusValue::for('in_progress'), $actorId, "{$log->side->label()}: بدأ الشغل");
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
            $finishedAt = now();

            $log->update([
                'status' => 'done',
                'finished_at' => $finishedAt,
                'duration_minutes' => $log->started_at
                    ? (int) $log->started_at->diffInMinutes($finishedAt)
                    : null,
            ]);

            $ticket = $log->ticket->refresh();

            if ($this->allSidesDone($ticket)) {
                $this->transition($ticket, TicketStatusValue::for('dev_done'), $actorId, 'كل الجهات خلصت');

                // No tester means nobody is going to verify it, so the ticket
                // waits for support or a manager rather than sitting in limbo. F16
                if ($ticket->tester_id !== null) {
                    $this->transition($ticket, TicketStatusValue::for('testing'), $actorId, 'في انتظار التيست');
                }
            }
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

        $open = DB::table('ticket_subtasks')
            ->where('ticket_id', $log->ticket_id)
            ->where('side', $log->side->value)
            ->whereNull('deleted_at')
            ->where('status', '!=', 'done')
            ->count();

        return $open === 0
            ? null
            : "لسه فيه {$open} صب تاسك مش خالصة على جهة {$log->side->label()}. خلّصها الأول.";
    }

    private function allSidesDone(Ticket $ticket): bool
    {
        $logs = $ticket->workLogs()->get(['status']);

        return $logs->isNotEmpty() && $logs->every(fn ($l) => $l->status === 'done');
    }

    /** F15 */
    public function approve(Ticket $ticket, int $adminId): Ticket
    {
        $ticket->update([
            'approval_status' => 'approved',
            'approved_by' => $adminId,
            'approved_at' => now(),
        ]);

        $ticket->statusHistory()->create([
            'from_status' => $ticket->status->value,
            'to_status' => $ticket->status->value,
            'user_id' => $adminId,
            'note' => 'تمت الموافقة',
        ]);

        return $ticket;
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
