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
        $this->authorize('update', $subtask);

        $data = $request->validate([
            'due_date' => ['required', 'date'],
        ], [], ['due_date' => 'تاريخ الاستحقاق']);

        $due = CarbonImmutable::parse($data['due_date']);

        // Keep the duration when the start date moves with it, so dragging a
        // 3-day task doesn't silently squash it to one.
        $attributes = ['due_date' => $due->toDateString()];

        if ($subtask->start_date !== null && $subtask->due_date !== null) {
            $span = $subtask->start_date->diffInDays($subtask->due_date);
            $attributes['start_date'] = $due->subDays($span)->toDateString();
        }

        $subtask->update($attributes);
        $this->subtasks->syncCounters($subtask->ticket);

        $slaDue = $subtask->ticket->sla_due_at;
        $overshoots = $slaDue !== null && $due->endOfDay()->gt($slaDue);

        return response()->json([
            'ok' => true,
            'due_date' => $subtask->due_date->toDateString(),
            'start_date' => $subtask->start_date?->toDateString(),
            // F13: a move past the SLA is confirmed, not blocked.
            'warning' => $overshoots
                ? "التاريخ ده بعد مهلة الـ SLA بتاعة {$subtask->ticket->ticket_number} ({$slaDue->translatedFormat('j M')})."
                : null,
        ]);
    }
}
