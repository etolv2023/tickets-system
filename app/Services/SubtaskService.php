<?php

namespace App\Services;

use App\Enums\SubtaskSide;
use App\Enums\SubtaskStatus;
use App\Models\PointRule;
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
     * @param  array<string, mixed>  $data
     */
    public function create(Ticket $ticket, array $data, int $createdBy): TicketSubtask
    {
        return DB::transaction(function () use ($ticket, $data, $createdBy) {
            $side = $data['side'] ?? SubtaskSide::Other;
            $side = $side instanceof SubtaskSide ? $side : SubtaskSide::from($side);

            // F06 role-assignment extension: a subtask tied to a role earns
            // from the role matrix instead of the side matrix — the two are
            // mutually exclusive, never both consulted for the same subtask.
            $defaults = isset($data['role_id']) && $data['role_id'] !== null && $data['role_id'] !== ''
                ? $this->defaultRolePoints($ticket, (int) $data['role_id'])
                : $this->defaultPoints($ticket, $side);

            // F18: the subtask carries its own points from the moment it's
            // created, prefilled from the matrix — but a caller sending an
            // explicit value (the create/edit form) always wins.
            if (! isset($data['points']) || $data['points'] === '' || $data['points'] === null) {
                [$data['points'], $data['rule_id']] = $defaults;
            } else {
                [, $data['rule_id']] = $defaults;
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
     * F18: the matrix value for this ticket's (type, scope) and this
     * subtask's side — exact (type, scope, side), else fallback
     * (type, 'any', side). Purely a default; the caller may still override
     * the points themselves. Side 'other' or no matching rule → zero and no
     * rule, same as a gap in the matrix always meant: zero, never an
     * exception.
     *
     * @return array{0: float, 1: int|null}
     */
    private function defaultPoints(Ticket $ticket, SubtaskSide $side): array
    {
        $pointSide = $side->toPointSide();

        if ($pointSide === null) {
            return [0.0, null];
        }

        $rules = PointRule::map();
        $type = $ticket->type->value;

        $rule = $rules["{$type}|{$ticket->scope->value}|{$pointSide->value}"]
            ?? $rules["{$type}|any|{$pointSide->value}"]
            ?? null;

        if ($rule === null || ! $rule->is_active) {
            return [0.0, null];
        }

        return [(float) $rule->points, $rule->id];
    }

    /**
     * F06 role-assignment extension: the matrix value for this ticket's type
     * and this subtask's role — no scope involved, one rule per (type, role).
     * Same zero-and-no-rule fallback as defaultPoints() for a gap or an
     * inactive row.
     *
     * @return array{0: float, 1: int|null}
     */
    private function defaultRolePoints(Ticket $ticket, int $roleId): array
    {
        $rule = PointRule::roleMap()["{$ticket->type->value}|{$roleId}"] ?? null;

        if ($rule === null || ! $rule->is_active) {
            return [0.0, null];
        }

        return [(float) $rule->points, $rule->id];
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
