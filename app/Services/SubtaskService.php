<?php

namespace App\Services;

use App\Enums\SubtaskStatus;
use App\Models\Ticket;
use App\Models\TicketSubtask;
use Illuminate\Support\Facades\DB;

/**
 * Subtasks and the counters they keep (F08).
 *
 * subtasks_total / subtasks_done are stored on the ticket, not COUNT()ed at
 * render time (§ 4.6). Every path that can change them goes through
 * syncCounters(), so the stored number can't drift from the rows.
 */
class SubtaskService
{
    /**
     * ★ (2026-07-23) No matrix, no lookup by side/scope/role — every subtask
     * starts at this flat default and is edited by hand from then on
     * (subtasks.manage already lets anyone who can touch the subtask edit its
     * points). F18 always paid exactly what a subtask carries; this just
     * stops pretending there's a formula behind the starting number.
     */
    private const DEFAULT_POINTS = 1.0;

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(Ticket $ticket, array $data, int $createdBy): TicketSubtask
    {
        return DB::transaction(function () use ($ticket, $data, $createdBy) {
            if (! isset($data['points']) || $data['points'] === '' || $data['points'] === null) {
                $data['points'] = self::DEFAULT_POINTS;
            }

            $subtask = $ticket->subtasks()->create($data + [
                'created_by' => $createdBy,
                // Appended to the end; drag-and-drop reorders later.
                'position' => (int) $ticket->subtasks()->max('position') + 1,
            ]);

            $this->syncCounters($ticket);

            return $subtask;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(TicketSubtask $subtask, array $data): TicketSubtask
    {
        return DB::transaction(function () use ($subtask, $data) {
            $status = isset($data['status']) ? SubtaskStatus::from($data['status']) : $subtask->status;

            // Timestamps follow the status rather than being asked for.
            if ($status === SubtaskStatus::InProgress && $subtask->started_at === null) {
                $data['started_at'] = now();
            }

            if ($status === SubtaskStatus::Done) {
                $data['completed_at'] = $subtask->completed_at ?? now();
            } elseif ($subtask->status === SubtaskStatus::Done) {
                // Moved back out of done — the completion time is no longer true.
                $data['completed_at'] = null;
            }

            // A reason only belongs to a blocked subtask; unblocking clears it.
            if ($status !== SubtaskStatus::Blocked) {
                $data['blocked_reason'] = null;
            }

            $subtask->update($data);
            $this->syncCounters($subtask->ticket);

            return $subtask;
        });
    }

    public function delete(TicketSubtask $subtask): void
    {
        DB::transaction(function () use ($subtask) {
            $ticket = $subtask->ticket;
            $subtask->delete();
            $this->syncCounters($ticket);
        });
    }

    /**
     * Drag-and-drop reordering (F08).
     *
     * @param  array<int, int>  $orderedIds
     */
    public function reorder(Ticket $ticket, array $orderedIds): void
    {
        DB::transaction(function () use ($ticket, $orderedIds) {
            // Only ids that actually belong to this ticket — the list comes from
            // the browser and could name anything.
            $owned = $ticket->subtasks()->pluck('id')->all();

            foreach (array_values(array_intersect($orderedIds, $owned)) as $index => $id) {
                TicketSubtask::where('id', $id)->update(['position' => $index]);
            }
        });
    }

    /**
     * Recomputes the stored counters and the ticket's rolled-up estimate.
     *
     * Called from every mutation above. If it is ever skipped, the ticket shows
     * "3/7" while the rows say otherwise — and nobody notices until a report is
     * wrong.
     */
    public function syncCounters(Ticket $ticket): void
    {
        // subtasks() carries a default orderBy('position') for display. An
        // aggregate SELECT with that ORDER BY still in place trips MySQL's
        // ONLY_FULL_GROUP_BY (error 1140: "Mixing of GROUP columns... is
        // illegal if there is no GROUP BY clause") — the column isn't
        // aggregated and there's no GROUP BY to license it. reorder() drops
        // the inherited ORDER BY for this one query.
        $counts = $ticket->subtasks()
            ->reorder()
            ->selectRaw('COUNT(*) total, SUM(status = ?) done, SUM(estimated_hours) estimate', [
                SubtaskStatus::Done->value,
            ])
            ->first();

        $attributes = [
            'subtasks_total' => (int) $counts->total,
            'subtasks_done' => (int) $counts->done,
        ];

        // F09: the ticket's estimate is the sum of its subtasks — but only once
        // there are subtasks. A manually-set estimate on a ticket with none is
        // the user's own number and must survive.
        if ((int) $counts->total > 0) {
            $attributes['original_estimate_hours'] = $counts->estimate;
        }

        $ticket->forceFill($attributes)->saveQuietly();
    }
}
