<?php

namespace App\Services;

use App\Models\Ticket;
use App\Models\TicketSubtask;
use App\Models\TimeEntry;
use Illuminate\Support\Facades\DB;

/**
 * Time logging and the rollups it keeps (F09).
 *
 * spent_hours lives on both the subtask and the ticket as a stored value, never
 * SUM()ed at render time (§ 4.6). Every write recomputes from the rows, so the
 * rollup can't drift.
 *
 * Time has no bearing on points — deliberately (PLAN.md § 5).
 */
class TimeTrackingService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function log(Ticket $ticket, array $data, int $userId): TimeEntry
    {
        return DB::transaction(function () use ($ticket, $data, $userId) {
            $entry = TimeEntry::create([
                'ticket_id' => $ticket->id,
                'subtask_id' => $data['subtask_id'] ?? null,
                'user_id' => $userId,
                'hours' => $data['hours'],
                'spent_on' => $data['spent_on'],
                'note' => $data['note'] ?? null,
            ]);

            $this->syncRollups($ticket, $entry->subtask);

            return $entry;
        });
    }

    public function delete(TimeEntry $entry): void
    {
        DB::transaction(function () use ($entry) {
            $ticket = $entry->ticket;
            $subtask = $entry->subtask;

            $entry->delete();

            $this->syncRollups($ticket, $subtask);
        });
    }

    /** Recomputes both rollups from the rows that actually exist. */
    public function syncRollups(Ticket $ticket, ?TicketSubtask $subtask = null): void
    {
        if ($subtask !== null) {
            $subtask->forceFill([
                'spent_hours' => (float) $subtask->timeEntries()->sum('hours'),
            ])->saveQuietly();
        }

        $ticket->forceFill([
            'spent_hours' => (float) $ticket->timeEntries()->sum('hours'),
        ])->saveQuietly();
    }

    /**
     * A week of one person's entries, grouped by day, for /my-timesheet (F09).
     *
     * @return array{entries: \Illuminate\Support\Collection, byDay: array<string, float>, total: float}
     */
    public function weekFor(int $userId, string $from, string $to): array
    {
        $entries = TimeEntry::query()
            ->with(['ticket:id,ticket_number,title', 'subtask:id,title'])
            ->where('user_id', $userId)
            ->between($from, $to)
            ->orderByDesc('spent_on')
            ->get();

        $byDay = $entries
            ->groupBy(fn (TimeEntry $e) => $e->spent_on->toDateString())
            ->map(fn ($group) => (float) $group->sum('hours'))
            ->all();

        return [
            'entries' => $entries,
            'byDay' => $byDay,
            'total' => (float) $entries->sum('hours'),
        ];
    }
}
