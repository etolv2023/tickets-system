<?php

namespace App\Http\Controllers;

use App\Http\Requests\Tickets\SubtaskStatusRequest;
use App\Models\TicketSubtask;
use App\Services\SubtaskService;
use Illuminate\Http\JsonResponse;

/**
 * Flipping one subtask's status in a single click (F08).
 *
 * Separate from SubtaskController for the same reason the calendar's drag
 * endpoint is: this is a one-field contract that answers json. Going through
 * SubtaskController::update would mean sending title and side back unchanged
 * on every click, because SubtaskRequest requires them.
 *
 * Mounted on the ticket page's subtask rows and, from phase 5, inside each
 * board card's accordion — the same control in both places.
 */
class SubtaskStatusController extends Controller
{
    public function __construct(private readonly SubtaskService $subtasks)
    {
    }

    public function update(SubtaskStatusRequest $request, TicketSubtask $subtask): JsonResponse
    {
        // SubtaskService::update() derives started_at / completed_at from the
        // status and re-syncs the ticket's counters, so nothing else to do.
        $this->subtasks->update($subtask, ['status' => $request->validated('status')]);

        $ticket = $subtask->ticket->refresh();

        return response()->json([
            'ok' => true,
            'status' => $subtask->status->value,
            'label' => $subtask->status->label(),
            'done' => $ticket->subtasks_done,
            'total' => $ticket->subtasks_total,
        ]);
    }
}
