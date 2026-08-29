<?php

namespace App\Services;

use App\Models\PointTransaction;
use App\Models\Ticket;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * ★ (2026-08-29) F18 — undoing a manual correction, by writing rather than erasing.
 *
 * The screen says «تعديل» and «حذف» because that is what the person is trying
 * to do. Underneath, neither exists: this service only ever inserts.
 *
 *   cancel()  → one reversing row. Same person, same role, same period, points
 *               negated, reverses_id pointing back at the original.
 *   replace() → that same reversing row, plus a corrected one beside it.
 *
 * The original row is never touched, so PointTransaction::booted() keeps
 * refusing every update and every delete with no exception carved into it. The
 * ledger reads as it always did: a sum. Cancelling a +32 and posting a −32
 * leaves the person's total exactly where it would have been had the mistake
 * never happened, and a report printed last month still matches what was paid.
 *
 * PERIOD: the reversal is posted into the ORIGINAL's period, not the current
 * month. «إلغاء» is meant to read as "this never counted", and in the ordinary
 * case — a mistake noticed the same month it was made — the two are the same
 * value anyway. The consequence worth knowing: cancelling a correction from a
 * month whose bonus was already paid changes that month's total after the fact.
 *
 * Takes the actor as a parameter and never reads auth() (CLAUDE.md § 3).
 */
class PointCorrectionService
{
    /**
     * «حذف» — the correction stops counting, and the record of it does not.
     *
     * @throws RuntimeException with a message meant for the user
     */
    public function cancel(PointTransaction $correction, int $userId, ?string $note = null): PointTransaction
    {
        $this->assertCancellable($correction);

        return DB::transaction(fn () => $this->reversalFor($correction, $userId, $note));
    }

    /**
     * «تعديل» — cancel what was written and post what should have been.
     *
     * Both halves in one transaction: a reversal with no replacement beside it
     * is a silent pay cut, so either both rows exist or neither does.
     *
     * @param  array<string, mixed>  $data  the same fields the create form takes
     * @return array{reversal: PointTransaction, replacement: PointTransaction}
     */
    public function replace(PointTransaction $correction, array $data, int $userId): array
    {
        $this->assertCancellable($correction);

        $ticket = $this->resolveTicket($data['ticket_number'] ?? null);

        return DB::transaction(function () use ($correction, $data, $userId, $ticket) {
            $reversal = $this->reversalFor($correction, $userId, 'اتعدّل');

            $replacement = PointTransaction::create([
                'user_id' => $data['user_id'],
                'ticket_id' => $ticket?->id,
                'role_id' => $data['role_id'],
                'points' => $data['points'],
                'type' => 'correction',
                'created_by' => $userId,
                // The original's period, matching its reversal — otherwise an
                // edit would quietly move points from one month into another.
                'period' => $correction->period,
                'reason' => $data['reason'],
                'replaces_id' => $correction->id,
            ]);

            return ['reversal' => $reversal, 'replacement' => $replacement];
        });
    }

    /**
     * The ticket a correction points at, looked up by its number.
     *
     * @throws RuntimeException when a number was typed and matches nothing
     */
    public function resolveTicket(?string $ticketNumber): ?Ticket
    {
        if (blank($ticketNumber)) {
            return null;
        }

        $ticket = Ticket::where('ticket_number', trim($ticketNumber))->first();

        if ($ticket === null) {
            throw new RuntimeException('مفيش تذكرة بالرقم ده.');
        }

        return $ticket;
    }

    /**
     * The reversing row itself.
     *
     * Negation goes through a float and back into DECIMAL(5,2). Exact at this
     * scale — the column holds at most 999.99 — and the database re-rounds to
     * two places on the way in regardless.
     */
    private function reversalFor(PointTransaction $correction, int $userId, ?string $note): PointTransaction
    {
        return PointTransaction::create([
            'user_id' => $correction->user_id,
            'ticket_id' => $correction->ticket_id,
            'role_id' => $correction->role_id,
            'side' => $correction->side?->value,
            'points' => -1 * (float) $correction->points,
            'type' => 'correction',
            'created_by' => $userId,
            'period' => $correction->period,
            'reason' => trim('إلغاء تصحيح #' . $correction->id
                . ($note ? ' (' . $note . ')' : '')
                . ' — ' . $correction->reason),
            'reverses_id' => $correction->id,
        ]);
    }

    /**
     * The four things that make a row untouchable.
     *
     * Every one of them is also enforced somewhere harder — the type by the
     * screen that only lists corrections, the double-cancel by UNIQUE on
     * reverses_id. These exist so the user gets a sentence instead of a
     * constraint violation.
     */
    private function assertCancellable(PointTransaction $correction): void
    {
        if ($correction->type !== 'correction') {
            throw new RuntimeException(
                'ده مش تصحيح يدوي — الصرف التلقائي وخصم التأخير بيتصلحوا من الصب تاسك نفسها.'
            );
        }

        if ($correction->isReversal()) {
            throw new RuntimeException('ده سطر إلغاء، وسطر الإلغاء مبيتلغيش.');
        }

        // Fresh from the database rather than a loaded relation: two admins on
        // this screen at the same moment is exactly the case this catches.
        if ($correction->reversal()->exists()) {
            throw new RuntimeException('التصحيح ده اتلغى قبل كده.');
        }
    }
}
