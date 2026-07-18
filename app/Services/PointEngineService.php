<?php

namespace App\Services;

use App\Enums\SubtaskStatus;
use App\Models\PointTransaction;
use App\Models\Ticket;
use App\Models\TicketSubtask;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Awards points on the FIRST entry into resolved — once, ever (F18).
 *
 * Every point traces to exactly one Done subtask. There is no ticket-level
 * matrix consulted here and no even split among several earners on a side —
 * each subtask carries its own points (SubtaskService sets a default from
 * point_rules, freely editable from then on), and whoever finished it takes
 * exactly that. A ticket resolved with nothing Done on a side pays nobody on
 * that side; there is deliberately no fallback to a named assignee, because a
 * side that was ever worked on always has a starter subtask (F06.3) and F07
 * refuses to finish a side while any of its subtasks are still open.
 *
 * These rows become money in a bonus run, so the guards are layered rather than
 * trusted one at a time:
 *
 *   1. points_awarded_at — the cheap check, and the one that expresses intent.
 *   2. The whole award runs in one transaction, with the ticket row locked, so
 *      two concurrent resolves can't both pass guard 1.
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
 */
class PointEngineService
{
    public function award(Ticket $ticket): void
    {
        // Guard 1: already paid. Reopening and resolving again earns nothing.
        if ($ticket->points_awarded_at !== null) {
            return;
        }

        // Guard 2: an unapproved or rejected feature earns nobody anything. F15
        if ($ticket->type->needsApproval() && $ticket->approval_status !== 'approved') {
            return;
        }

        if (! Schema::hasTable('ticket_subtasks')) {
            return;
        }

        DB::transaction(function () use ($ticket) {
            // Lock the ticket for the length of the award. A second resolve
            // arriving now waits here, then sees points_awarded_at set.
            $locked = Ticket::whereKey($ticket->id)->lockForUpdate()->first();

            if ($locked === null || $locked->points_awarded_at !== null) {
                return;
            }

            $period = ($locked->resolved_at ?? now())->format('Y-m');

            $subtasks = TicketSubtask::query()
                ->where('ticket_id', $locked->id)
                ->whereNull('deleted_at')
                ->where('status', SubtaskStatus::Done->value)
                ->whereNotNull('assignee_id')
                ->where('points', '>', 0)
                ->get();

            foreach ($subtasks as $subtask) {
                $this->awardSubtask($locked, $subtask, $period);
            }

            $locked->forceFill(['points_awarded_at' => now()])->saveQuietly();
            $ticket->forceFill(['points_awarded_at' => $locked->points_awarded_at])->syncOriginal();
        });
    }

    private function awardSubtask(Ticket $ticket, TicketSubtask $subtask, string $period): void
    {
        $side = $subtask->side->toPointSide();

        // 'other' is not evidence of whose work this was — no side, no award.
        if ($side === null) {
            return;
        }

        try {
            PointTransaction::create([
                'user_id' => $subtask->assignee_id,
                'ticket_id' => $ticket->id,
                'subtask_id' => $subtask->id,
                'side' => $side->value,
                'points' => $subtask->points,
                'type' => 'award',
                'rule_id' => $subtask->rule_id,
                'period' => $period,
                'reason' => "{$ticket->type->label()} — {$side->label()} ({$subtask->title})",
            ]);
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
