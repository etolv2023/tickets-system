<?php

namespace App\Services;

use App\Models\PointTransaction;
use App\Models\Ticket;
use App\Models\TicketSubtask;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Awards points to each Done subtask exactly once, ever (F18).
 *
 * Every point traces to exactly one Done subtask. There is no ticket-level
 * matrix consulted here and no even split among several earners on a side —
 * each subtask carries its own points (a flat default from SubtaskService,
 * freely editable from then on), and whoever finished it takes exactly that.
 * A ticket resolved with nothing Done on a side pays nobody on
 * that side; there is deliberately no fallback to a named assignee, because a
 * side that was ever worked on always has a starter subtask (F06.3) and F07
 * refuses to finish a side while any of its subtasks are still open.
 *
 * These rows become money in a bonus run, so the guards are layered rather than
 * trusted one at a time:
 *
 *   1. whereDoesntHave('pointTransaction') — only subtasks never paid before
 *      are even considered. This is per-SUBTASK, not per-ticket (2026-07-21
 *      fix): a ticket can be resolved, reopened, and resolved again — e.g. a
 *      devops subtask added after the first resolve — and the subtasks that
 *      earned points the first time are skipped while the new one still gets
 *      paid. points_awarded_at on the ticket is now just a "first paid at"
 *      timestamp for reporting, not a gate.
 *   2. The whole award runs in one transaction, with the ticket row locked, so
 *      two concurrent resolves can't both select the same eligible subtask.
 *   3. UNIQUE(subtask_id) — the last line. A subtask can only ever be paid
 *      once; if a race ever beat 1 and 2, the database refuses the duplicate.
 *
 * Deliberate choices, all from F18:
 *   - period comes from resolved_at, not now(): a ticket resolved in March and
 *     stamped in April belongs to March's bonus.
 *   - Editing a subtask's points after it was paid is NOT retrospective — the
 *     ledger keeps what it paid.
 *   - The same person on frontend AND backend earns two separate rows. Intended.
 *   - A subtask with no assignee, zero points, or side 'other' earns nothing —
 *     never an exception. Manual corrections (PointCorrectionService) are the
 *     only path for anything the automatic award doesn't cover.
 *   - Logged time has no bearing on any of this (PLAN.md § 5).
 *   - ★ (2026-08-05) A subtask finished after its due date earns nothing and is
 *     docked instead. This class no longer owns that rule — LatePenaltyService
 *     does, because the 06:00 sweep charges it long before any resolve. All
 *     that happens here is the refusal to pay.
 *
 * Distribution is fully role-based (2026-07-24): every assigned role — devops
 * included — gets a starter subtask on assignment (TicketWorkflowService), so
 * every participant's work is tracked and paid through the one subtask loop
 * below. The old devops participation special-case (a flat 0.5 for a devops
 * assignee with no subtask) is gone with it — devops earns exactly like every
 * other role now.
 */
class PointEngineService
{
    public function __construct(private readonly LatePenaltyService $penalties)
    {
    }

    public function award(Ticket $ticket): void
    {
        // Guard: an unapproved or rejected feature earns nobody anything. F15
        if ($ticket->type->needsApproval() && $ticket->approval_status !== 'approved') {
            return;
        }

        if (! Schema::hasTable('ticket_subtasks')) {
            return;
        }

        DB::transaction(function () use ($ticket) {
            // Lock the ticket for the length of the award, so two concurrent
            // resolves can't both select the same eligible subtask.
            $locked = Ticket::whereKey($ticket->id)->lockForUpdate()->first();

            if ($locked === null) {
                return;
            }

            $period = ($locked->resolved_at ?? now())->format('Y-m');

            $subtasks = TicketSubtask::query()
                ->where('ticket_id', $locked->id)
                ->whereNull('deleted_at')
                ->where('status', 'done')
                ->whereNotNull('assignee_id')
                ->where('points', '>', 0)
                ->whereDoesntHave('pointTransaction')
                // F06 role-assignment extension: awardSubtask() reads
                // ->role->name_ar for a role-based subtask.
                ->with('role:id,name_ar')
                ->get();

            foreach ($subtasks as $subtask) {
                $this->awardSubtask($locked, $subtask, $period);
            }

            // Set once, on the first payout only — a historical marker, not a gate.
            if ($locked->points_awarded_at === null) {
                $locked->forceFill(['points_awarded_at' => now()])->saveQuietly();
                $ticket->forceFill(['points_awarded_at' => $locked->points_awarded_at])->syncOriginal();
            }
        });
    }

    /** Public so the points:backfill command can reuse the exact same award logic. */
    public function awardSubtask(Ticket $ticket, TicketSubtask $subtask, string $period): void
    {
        // F06 role-assignment extension: a subtask tied to a role (support/
        // manager/admin/custom) pays that role directly — same mechanism as
        // every fixed side, just keyed by role_id instead of PointSide.
        if ($subtask->role_id !== null) {
            $this->createTransaction($ticket, $subtask, $period, [
                'role_id' => $subtask->role_id,
                'earner' => $subtask->role->name_ar,
            ]);

            return;
        }

        $side = $subtask->side->toPointSide();

        // 'other' is not evidence of whose work this was — no side, no award.
        if ($side === null) {
            return;
        }

        $this->createTransaction($ticket, $subtask, $period, [
            'side' => $side->value,
            'earner' => $side->label(),
        ]);
    }

    /**
     * The moment the work actually landed.
     *
     * SubtaskService stamps completed_at as a subtask goes done, so that is
     * normally the answer. The fallbacks are for rows that predate it — the
     * ticket's own resolution time, then the clock.
     */
    private function finishedAt(Ticket $ticket, TicketSubtask $subtask): \Illuminate\Support\Carbon
    {
        return $subtask->completed_at ?? $ticket->resolved_at ?? now();
    }

    /**
     * ★ (2026-08-05) Where lateness decides whether anything is paid at all.
     *
     * A subtask delivered after its due date earns nothing — ever. It is not
     * paid less and it is not paid later; it is docked instead, at MINUS its
     * points. The rule is deliberately blind to how late and to why, because
     * the point of a due date is that it is a line, not a slope.
     *
     * The deduction itself is LatePenaltyService's job, not this class's. That
     * service owns the rule because the 06:00 sweep — not this method — is what
     * normally charges it: a ticket can sit open for weeks, and waiting for
     * resolve would mean nobody sees the cost until payout day. By the time a
     * late subtask reaches this method it has usually been charged already, and
     * charge() returns false because UNIQUE(subtask_id, charge_key) refuses the
     * repeat. What this call is really for is the gap the sweep cannot see: a
     * subtask backdated, finished and resolved between two runs never sat
     * overdue at 6 AM, yet it was still delivered late.
     *
     * A subtask with no due date is never late. Nothing was promised, so nothing
     * was missed.
     *
     * Which is why the due date stopped being ordinary planning the same day:
     * it is gated behind subtasks.schedule (TicketSubtaskPolicy::schedule), so
     * the person who stands to lose the points is not the person who draws the
     * line they lose them by.
     *
     * The comparison is TicketSubtask::finishedLate() rather than a copy here —
     * the ticket page warns the owner before the fact through the same method,
     * and two versions of this rule drifting apart would mean the screen
     * promising points the payout doesn't give.
     *
     * @param  array{side?: string, role_id?: int, earner: string}  $earner
     */
    private function createTransaction(Ticket $ticket, TicketSubtask $subtask, string $period, array $earner): void
    {
        $finished = $this->finishedAt($ticket, $subtask);

        if ($subtask->finishedLate($finished)) {
            $this->penalties->charge($ticket, $subtask, $finished);

            return;
        }

        $label = $earner['earner'];
        unset($earner['earner']);

        $head = "{$ticket->type->label()} — {$label} (";
        // Titles are validated at max:255 on their own, so an unguarded
        // "{type} — {earner} ({title})" can overflow VARCHAR(255) and abort the
        // resolve in strict mode. The title is what gives.
        $room = 255 - mb_strlen($head) - 1;
        $title = mb_strlen($subtask->title) > $room
            ? mb_substr($subtask->title, 0, max(0, $room - 1)) . '…'
            : $subtask->title;

        try {
            PointTransaction::create([
                'user_id' => $subtask->assignee_id,
                'ticket_id' => $ticket->id,
                'subtask_id' => $subtask->id,
                'points' => $subtask->points,
                'type' => 'award',
                'charge_key' => 'award',
                'period' => $period,
                'reason' => $head . $title . ')',
            ] + $earner);
        } catch (UniqueConstraintViolationException) {
            // Guard 3 fired: this exact subtask already has a row. Nothing to
            // do — the point of the index is that it makes this harmless.
            Log::warning('duplicate point award blocked by the unique index', [
                'ticket' => $ticket->ticket_number,
                'subtask' => $subtask->id,
                'user' => $subtask->assignee_id,
            ]);
        }
    }

}
