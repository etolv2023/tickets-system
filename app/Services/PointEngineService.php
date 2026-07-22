<?php

namespace App\Services;

use App\Enums\PointSide;
use App\Enums\SubtaskSide;
use App\Enums\SubtaskStatus;
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
 *     ledger keeps what it paid, same as editing the matrix never was.
 *   - The same person on frontend AND backend earns two separate rows. Intended.
 *   - A subtask with no assignee, zero points, or side 'other' earns nothing —
 *     never an exception. Manual corrections (PointCorrectionService) are the
 *     only path for anything the automatic award doesn't cover.
 *   - Logged time has no bearing on any of this (PLAN.md § 5).
 *   - Exception: devops has no starter subtask on assignment (F06.3 only
 *     covers WorkSide::Frontend/Backend), so awardDevopsParticipation() below
 *     pays a flat participation credit when devops is assigned with no
 *     subtask to show for it — see its docblock.
 */
class PointEngineService
{
    /**
     * Flat, one-time credit for a devops person assigned to a ticket
     * (devops_id) who never got a dedicated subtask — a fixed amount, not
     * looked up per ticket type, because it is a participation credit for
     * untracked work, not a matrix rate for tracked work (2026-07-21).
     */
    private const DEVOPS_PARTICIPATION_POINTS = 0.5;

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
                ->where('status', SubtaskStatus::Done->value)
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

            $this->awardDevopsParticipation($locked, $period);

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
                'reason' => "{$ticket->type->label()} — {$subtask->role->name_ar} ({$subtask->title})",
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
            'reason' => "{$ticket->type->label()} — {$side->label()} ({$subtask->title})",
        ]);
    }

    /** @param  array{side?: string, role_id?: int, reason: string}  $earner */
    private function createTransaction(Ticket $ticket, TicketSubtask $subtask, string $period, array $earner): void
    {
        try {
            PointTransaction::create([
                'user_id' => $subtask->assignee_id,
                'ticket_id' => $ticket->id,
                'subtask_id' => $subtask->id,
                'points' => $subtask->points,
                'type' => 'award',
                'period' => $period,
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

    /**
     * F18 addition (2026-07-21): devops is assignable at the ticket level
     * (devops_id) without ever getting a subtask — unlike frontend/backend,
     * which always get a starter subtask the moment they're assigned
     * (TicketWorkflowService::assign() only auto-creates one for WorkSide
     * cases, and devops isn't one). Without this, real but untracked devops
     * work — a config tweak, a quick server check — earned nothing, ever.
     *
     * Either this flat credit OR per-subtask points, never both: the moment a
     * devops subtask exists for this person on this ticket, their work is
     * tracked and paid through the normal subtask loop above instead. Paid
     * once per ticket, ever — not backed by a unique index (no natural
     * column to key one on with subtask_id null), so the ticket lock this
     * runs under is the only guard against a concurrent double-pay, same
     * threat model as guard 2 in the class docblock.
     */
    public function awardDevopsParticipation(Ticket $ticket, string $period): void
    {
        if ($ticket->devops_id === null) {
            return;
        }

        $alreadyPaidFlat = PointTransaction::where('ticket_id', $ticket->id)
            ->where('user_id', $ticket->devops_id)
            ->where('side', PointSide::Devops->value)
            ->whereNull('subtask_id')
            ->exists();

        if ($alreadyPaidFlat) {
            return;
        }

        $hasTrackedSubtask = TicketSubtask::query()
            ->where('ticket_id', $ticket->id)
            ->whereNull('deleted_at')
            ->where('side', SubtaskSide::Devops->value)
            ->where('assignee_id', $ticket->devops_id)
            ->exists();

        if ($hasTrackedSubtask) {
            return;
        }

        PointTransaction::create([
            'user_id' => $ticket->devops_id,
            'ticket_id' => $ticket->id,
            'subtask_id' => null,
            'side' => PointSide::Devops->value,
            'points' => self::DEVOPS_PARTICIPATION_POINTS,
            'type' => 'award',
            'period' => $period,
            'reason' => "{$ticket->type->label()} — مشاركة ديف أوبس من غير صب تاسك مخصص",
        ]);
    }
}
