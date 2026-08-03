<?php

namespace App\Services;

use App\Casts\SubtaskStatusValue;
use App\Models\Ticket;
use App\Models\TicketSubtask;
use App\Models\User;
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
     * @param  array<string, mixed>  $data
     */
    public function create(Ticket $ticket, array $data, int $createdBy): TicketSubtask
    {
        return DB::transaction(function () use ($ticket, $data, $createdBy) {
            $data = $this->roleFollowsAssignee($data);

            if (! isset($data['points']) || $data['points'] === '' || $data['points'] === null) {
                // ★ (2026-07-23) No matrix, no lookup by side/scope/role — a
                // subtask starts at a default and is edited by hand from then on
                // (subtasks.manage already lets anyone who can touch it edit its
                // points). F18 always paid exactly what a subtask carries.
                //
                // ★ (2026-08-02) That starting number is the ticket type's own
                // weight, so a فيتشر opens heavier than a بج without anyone
                // retyping it per row. Still one lookup, still no formula.
                //
                // Applies to every path equally — the auto starter (F06.3), the
                // rows typed on the create form (F08), and the ticket page's add
                // button. A subtask added by hand is not a second-class subtask.
                // Not ?: — a type deliberately weighted 0 must stay 0.
                $data['points'] = $ticket->type->defaultPoints();
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
            $data = $this->roleFollowsAssignee($data);

            $status = isset($data['status']) ? SubtaskStatusValue::for($data['status']) : $subtask->status;

            // Timestamps follow the status rather than being asked for.
            if ($status->isInProgress() && $subtask->started_at === null) {
                $data['started_at'] = now();
            }

            if ($status->isDone()) {
                $data['completed_at'] = $subtask->completed_at ?? now();
            } elseif ($subtask->status->isDone()) {
                // Moved back out of done — the completion time is no longer true.
                $data['completed_at'] = null;
            }

            // A reason only belongs to a status that requires one; leaving it
            // clears it.
            if (! $status->needsReason()) {
                $data['blocked_reason'] = null;
            }

            $subtask->update($data);
            $this->syncCounters($subtask->ticket);

            return $subtask;
        });
    }

    /**
     * ★ (2026-07-23) A subtask's role follows its owner. The role picker was
     * removed from the form, so when the assignee is set here the role is derived
     * from that person's own role — the two can never drift, and there is one
     * input (the assignee) that decides both. Clearing the assignee clears the
     * role (a general, unowned step).
     *
     * Only applies when the caller did NOT pass an explicit role_id: the
     * distribution starter (F06.3) and the follow-up subtask set role_id
     * themselves to the exact role slot their F07 finish gate reads, and that
     * explicit choice wins. Points are unaffected — a flat point pays the
     * assignee whatever the role.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function roleFollowsAssignee(array $data): array
    {
        if (array_key_exists('role_id', $data) || ! array_key_exists('assignee_id', $data)) {
            return $data;
        }

        $data['role_id'] = $data['assignee_id']
            ? User::whereKey($data['assignee_id'])->value('role_id')
            : null;

        return $data;
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
                'done',
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
