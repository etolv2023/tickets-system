<?php

namespace App\Services;

use App\Enums\PointSide;
use App\Models\PointRule;
use App\Models\PointTransaction;
use App\Models\Ticket;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Awards points on the FIRST entry into resolved — once, ever (F18).
 *
 * These rows become money in a bonus run, so the guards are layered rather than
 * trusted one at a time:
 *
 *   1. points_awarded_at — the cheap check, and the one that expresses intent.
 *   2. The whole award runs in one transaction, with the ticket row locked, so
 *      two concurrent resolves can't both pass guard 1.
 *   3. UNIQUE(ticket_id, user_id, side) — the last line. If a race ever beat
 *      1 and 2, the database refuses the duplicate rather than paying twice.
 *
 * Deliberate choices, all from F18:
 *   - period comes from resolved_at, not now(): a ticket resolved in March and
 *     stamped in April belongs to March's bonus.
 *   - Editing a rule is NOT retrospective. The ledger keeps what it paid.
 *   - The same person on frontend AND backend earns two separate rows. Intended.
 *   - No matching rule = zero + a warning, never an exception. A missing rule
 *     must not block a ticket from being resolved.
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

        if (! Schema::hasTable('point_rules')) {
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

            foreach (PointSide::cases() as $side) {
                $this->awardSide($locked, $side, $period);
            }

            $locked->forceFill(['points_awarded_at' => now()])->saveQuietly();
            $ticket->forceFill(['points_awarded_at' => $locked->points_awarded_at])->syncOriginal();
        });
    }

    private function awardSide(Ticket $ticket, PointSide $side, string $period): void
    {
        $userId = $ticket->{$side->participantColumn()};

        if ($userId === null) {
            return;
        }

        $rule = $this->resolveRule($ticket, $side);

        if ($rule === null) {
            // F18: no rule means zero and a warning — never an exception. A gap
            // in the matrix must not stop a ticket being resolved.
            Log::warning('point rule missing', [
                'ticket' => $ticket->ticket_number,
                'type' => $ticket->type->value,
                'scope' => $ticket->scope->value,
                'side' => $side->value,
            ]);

            return;
        }

        if (! $rule->is_active || (float) $rule->points == 0.0) {
            return;
        }

        try {
            PointTransaction::create([
                'user_id' => $userId,
                'ticket_id' => $ticket->id,
                'side' => $side->value,
                'points' => $rule->points,
                'rule_id' => $rule->id,
                'period' => $period,
                'reason' => "{$ticket->type->label()} — {$side->label()}",
            ]);
        } catch (UniqueConstraintViolationException) {
            // Guard 3 fired: this exact row already exists. Nothing to do —
            // the point of the index is that it makes this harmless.
            Log::warning('duplicate point award blocked by the unique index', [
                'ticket' => $ticket->ticket_number,
                'user' => $userId,
                'side' => $side->value,
            ]);
        }
    }

    /**
     * F18: exact (type, scope, side), else fall back to (type, 'any', side).
     * An inquiry has no meaningful scope, so its rules live under 'any'.
     */
    private function resolveRule(Ticket $ticket, PointSide $side): ?PointRule
    {
        $rules = PointRule::map();
        $type = $ticket->type->value;

        return $rules["{$type}|{$ticket->scope->value}|{$side->value}"]
            ?? $rules["{$type}|any|{$side->value}"]
            ?? null;
    }

    /*
     * On corrections — a conflict between the two specs, left unresolved on
     * purpose rather than guessed at:
     *
     *   PLAN.md § 4.7 requires UNIQUE(ticket_id, user_id, side), and PROMPT.md
     *   calls it "خط الدفاع الأخير" against double-spending.
     *
     *   F18 says a correction is a new row with negative points.
     *
     * Both cannot hold: a correcting row carries the same (ticket, user, side)
     * as the row it corrects, and the unique index refuses it.
     *
     * The index is implemented, because it is the stronger and more explicitly
     * stated guarantee, and because these rows become money. That means there is
     * no correction path yet — and shipping a reverse() that always throws would
     * be worse than not shipping one. Resolving this needs a decision:
     * either a discriminator column in the unique key, or corrections handled
     * outside the ledger.
     */
}
