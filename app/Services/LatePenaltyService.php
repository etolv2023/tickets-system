<?php

namespace App\Services;

use App\Models\PointTransaction;
use App\Models\Setting;
use App\Models\Ticket;
use App\Models\TicketSubtask;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * ★ (2026-08-05) The late-delivery deduction (F18 extension).
 *
 * A subtask that carried a due date and crossed it does not merely forfeit its
 * points — it is written into the ledger at MINUS the same figure. Blind to how
 * late and to why: an hour past midnight and two weeks past cost the same,
 * because the point of a due date is that it is a line, not a slope. And
 * finishing it afterwards changes nothing; there is no path where work
 * delivered after its date earns anything.
 *
 * Two callers, one rule:
 *
 *   1. ChargeLatePenalties — the 06:00 sweep. This is the primary one, and the
 *      reason the deduction does not wait for the ticket to be resolved: a
 *      ticket can sit open for weeks, and a penalty nobody sees until payout
 *      day is a penalty that teaches nothing.
 *   2. PointEngineService, at resolve. The safety net for the gap the sweep
 *      cannot cover — a subtask backdated, finished and resolved between two
 *      runs never sat overdue at 6 AM, but it was still delivered late.
 *
 * HOW OFTEN a subtask can be docked is the one part an admin controls, through
 * the «تراكم التأخير على التاسكات» setting:
 *
 *   off (default) → once per subtask, ever. Being late is a single event.
 *   on            → once every morning it is still overdue AND still unfinished.
 *                   Standing still costs more than being late once, and the
 *                   reason line says which day's charge each row is.
 *
 * Accumulation deliberately stops the moment the subtask is done. Otherwise a
 * finished-but-late subtask on a long-running ticket would keep billing its
 * owner forever for work that is already delivered.
 */
class LatePenaltyService
{
    public const SETTING = 'late_penalty_accumulates';

    /**
     * The 06:00 sweep: dock everything that is late and not yet paid for it.
     *
     * @return array{charged: int, skipped: int, accumulating: bool}
     */
    public function chargeOverdue(?Carbon $asOf = null): array
    {
        $asOf ??= now();
        $accumulates = $this->accumulates();

        $charged = 0;
        $skipped = 0;

        $this->overdueQuery($asOf)->chunkById(200, function ($subtasks) use (&$charged, &$skipped, $asOf, $accumulates) {
            foreach ($subtasks as $subtask) {
                if (! $this->owesChargeToday($subtask, $accumulates, $asOf)) {
                    $skipped++;

                    continue;
                }

                $this->charge($subtask->ticket, $subtask, $asOf) ? $charged++ : $skipped++;
            }
        });

        return ['charged' => $charged, 'skipped' => $skipped, 'accumulating' => $accumulates];
    }

    /**
     * What --dry-run shows. Same query and same decision as chargeOverdue(),
     * with nothing written — a preview that runs different code from the real
     * run is a preview of nothing.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function previewOverdue(?Carbon $asOf = null): Collection
    {
        $asOf ??= now();
        $accumulates = $this->accumulates();

        return $this->overdueQuery($asOf)
            ->with('assignee:id,name')
            ->get()
            ->filter(fn (TicketSubtask $s) => $this->owesChargeToday($s, $accumulates, $asOf)
                && $this->isChargeable($s->ticket, $s))
            ->map(fn (TicketSubtask $s) => [
                'title' => $s->title,
                'ticket' => $s->ticket->ticket_number,
                'assignee' => $s->assignee?->name ?? '—',
                'due' => $s->due_date->toDateString(),
                'points' => (float) $s->points,
                'charged_before' => $s->point_transactions_count > 0,
            ])
            ->values();
    }

    /**
     * Whether this subtask owes a deduction on this run.
     *
     * The lateness question is asked through TicketSubtask::finishedLate(), the
     * same method the resolve path and the ticket screen use, rather than
     * inferred from the SQL filter. That filter is only a coarse net — a subtask
     * DONE ON TIME still has a due date in the past forever, so it keeps
     * matching "due before today" for the rest of its life. Trusting the net
     * alone would dock the people who delivered on time, every morning, until
     * the ticket was resolved.
     *
     * Never charged → yes, this is the one charge it gets.
     * Charged before → only when accumulation is on AND the work is still not
     * in. Accumulation stops the moment the subtask is done, or a
     * finished-but-late subtask on a long-running ticket would keep billing its
     * owner every morning for work already delivered.
     */
    private function owesChargeToday(TicketSubtask $subtask, bool $accumulates, Carbon $asOf): bool
    {
        // An open subtask is judged against the morning of the run; a finished
        // one against when it was actually finished.
        $finished = $subtask->status->isDone() ? $subtask->completed_at : $asOf;

        if (! $subtask->finishedLate($finished)) {
            return false;
        }

        if ($subtask->point_transactions_count === 0) {
            return true;
        }

        return $accumulates && ! $subtask->status->isDone();
    }

    /**
     * Writes one deduction row. Returns false when there was nothing to write.
     *
     * The insert is allowed to race and lose: UNIQUE(subtask_id, charge_key) is
     * what actually guarantees one charge per subtask per day, so a second
     * caller on the same morning is refused by the database rather than by a
     * check that a concurrent request could have slipped past.
     */
    public function charge(Ticket $ticket, TicketSubtask $subtask, ?Carbon $asOf = null): bool
    {
        $asOf ??= now();

        if (! $this->isChargeable($ticket, $subtask)) {
            return false;
        }

        try {
            PointTransaction::create([
                'user_id' => $subtask->assignee_id,
                'ticket_id' => $ticket->id,
                'subtask_id' => $subtask->id,
                'side' => $subtask->role_id === null ? $subtask->side->toPointSide()?->value : null,
                'role_id' => $subtask->role_id,
                'points' => -$subtask->points,
                'type' => 'penalty',
                'charge_key' => 'penalty:' . $asOf->toDateString(),
                // The month the deduction lands in is the month it was charged.
                // Unlike an award it has no resolved_at to belong to — the work
                // is, by definition, not delivered.
                'period' => $asOf->format('Y-m'),
                'reason' => $this->reason($ticket, $subtask, $asOf),
            ]);

            return true;
        } catch (UniqueConstraintViolationException) {
            Log::warning('duplicate late penalty blocked by the unique index', [
                'ticket' => $ticket->ticket_number,
                'subtask' => $subtask->id,
                'day' => $asOf->toDateString(),
            ]);

            return false;
        }
    }

    /** Whether this subtask has ever been docked. */
    public function wasCharged(TicketSubtask $subtask): bool
    {
        return PointTransaction::where('subtask_id', $subtask->id)
            ->where('type', 'penalty')
            ->exists();
    }

    public function accumulates(): bool
    {
        return (bool) Setting::get(self::SETTING, false);
    }

    /**
     * The guards, in one place so the sweep and the resolve path cannot drift.
     * Mirrors PointEngineService's own filters — a subtask that could never
     * have earned must not be able to lose either.
     */
    private function isChargeable(Ticket $ticket, TicketSubtask $subtask): bool
    {
        // An unapproved or rejected feature pays nobody, so it docks nobody. F15
        if ($ticket->type->needsApproval() && $ticket->approval_status !== 'approved') {
            return false;
        }

        return $subtask->assignee_id !== null
            && (float) $subtask->points > 0
            && $subtask->due_date !== null
            // 'other' names no earner, so there is nobody to charge.
            && ($subtask->role_id !== null || $subtask->side->toPointSide() !== null);
    }

    /**
     * Subtasks that are late right now and might owe a charge.
     *
     * "Late" is due_date strictly before today: a subtask due today is not late
     * until today is over, which is why the sweep runs in the morning and looks
     * backwards rather than at midnight and looks at itself.
     *
     * Resolved tickets are excluded — their points were settled at resolve, and
     * re-opening that is what a manual correction is for. Rows already carrying
     * a penalty are not filtered out in SQL: whether they owe another one is the
     * accumulation question, and the caller answers it with the count.
     */
    private function overdueQuery(Carbon $asOf)
    {
        return TicketSubtask::query()
            ->whereNull('deleted_at')
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', $asOf->toDateString())
            ->whereNotNull('assignee_id')
            ->where('points', '>', 0)
            // Coarse net only — owesChargeToday() makes the real call. This
            // keeps the finished-on-time majority out of memory rather than
            // loading every subtask that ever had a past due date. Late for a
            // done subtask means completed_at past the END of the due day, which
            // is the same as on or after the next day at 00:00.
            ->where(fn ($q) => $q
                ->where('status', '!=', 'done')
                ->orWhereNull('completed_at')
                ->orWhereRaw('completed_at >= DATE_ADD(due_date, INTERVAL 1 DAY)'))
            ->whereHas('ticket', fn ($q) => $q->whereNull('resolved_at'))
            ->withCount(['pointTransactions as point_transactions_count' => fn ($q) => $q->where('type', 'penalty')])
            ->with(['ticket', 'role:id,name_ar']);
    }

    /**
     * The ledger's sentence of explanation, in `reason` (VARCHAR 255).
     *
     * A bare negative number in a bonus run is an argument waiting to happen, so
     * the row carries the date it was due, how far past it is, and — when this
     * is a repeat charge — that it is a repeat and why more than one exists.
     *
     * The subtask title is what gets trimmed when the sentence runs long, never
     * the explanation: titles are validated at max:255 on their own, so an
     * unguarded sentence could overflow the column and abort the whole sweep.
     */
    private function reason(Ticket $ticket, TicketSubtask $subtask, Carbon $asOf): string
    {
        $earner = $subtask->role_id !== null
            ? $subtask->role->name_ar
            : $subtask->side->toPointSide()->label();

        $days = (int) $subtask->due_date->copy()->startOfDay()->diffInDays($asOf->copy()->startOfDay());
        $due = $subtask->due_date->translatedFormat('j M Y');

        $note = $this->wasCharged($subtask)
            ? "خصم تأخير متراكم ({$asOf->translatedFormat('j M')}): لسه متأخرة عن {$due} بـ {$days} يوم — التراكم مفعّل فبيتخصم كل يوم"
            : "خصم تأخير: كانت مستحقة {$due} ومتأخرة {$days} يوم";

        $head = "{$ticket->type->label()} — {$earner} (";
        $tail = ") — {$note}";

        $room = 255 - mb_strlen($head) - mb_strlen($tail);
        $title = mb_strlen($subtask->title) > $room
            ? mb_substr($subtask->title, 0, max(0, $room - 1)) . '…'
            : $subtask->title;

        return $head . $title . $tail;
    }
}
