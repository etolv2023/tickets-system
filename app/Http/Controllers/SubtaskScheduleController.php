<?php

namespace App\Http\Controllers;

use App\Models\TicketSubtask;
use App\Services\SubtaskService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Dragging a subtask to another day (F13).
 *
 * Separate from SubtaskController because this is the calendar's contract: it
 * moves dates only, answers json, and reports the SLA overshoot rather than
 * refusing it — the plan is allowed to overrun, but somebody has to see it.
 */
class SubtaskScheduleController extends Controller
{
    public function __construct(private readonly SubtaskService $subtasks)
    {
    }

    public function move(Request $request, TicketSubtask $subtask): JsonResponse
    {
        // ★ (2026-08-05) 'schedule' REPLACES the old 'update' check rather than
        // joining it. This endpoint does exactly one thing — move a due date —
        // and a subtask finished after its due date is docked its points, so
        // that is the single most valuable field on the row.
        //
        // Keeping 'update' alongside it would have defeated the point: update()
        // requires owning the subtask, so the manager the permission was created
        // for got a 403 on every row that belonged to a developer — which is
        // every row they would ever need to reschedule. schedule() carries the
        // ticket-visibility and lock checks itself.
        $this->authorize('schedule', [$subtask, $subtask->ticket]);

        $data = $request->validate([
            'due_date' => ['required', 'date'],
        ], [], ['due_date' => 'تاريخ الاستحقاق']);

        $due = CarbonImmutable::parse($data['due_date']);

        // A subtask carries one date, so a drag is a move, not a span shift.
        $subtask->update(['due_date' => $due->toDateString()]);
        $this->subtasks->syncCounters($subtask->ticket);

        $slaDue = $subtask->ticket->sla_due_at;
        $overshoots = $slaDue !== null && $due->endOfDay()->gt($slaDue);

        return response()->json([
            'ok' => true,
            'due_date' => $subtask->due_date->toDateString(),
            // F13: a move past the SLA is confirmed, not blocked.
            'warning' => $overshoots
                ? "التاريخ ده بعد مهلة الـ SLA بتاعة {$subtask->ticket->ticket_number} ({$slaDue->translatedFormat('j M')})."
                : null,
        ]);
    }
}
