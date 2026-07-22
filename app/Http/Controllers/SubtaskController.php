<?php

namespace App\Http\Controllers;

use App\Http\Requests\Tickets\SubtaskRequest;
use App\Models\Ticket;
use App\Models\TicketSubtask;
use App\Services\NotificationService;
use App\Services\SubtaskService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SubtaskController extends Controller
{
    public function __construct(
        private readonly SubtaskService $subtasks,
        private readonly NotificationService $notifications,
    ) {
    }

    public function store(SubtaskRequest $request, Ticket $ticket): RedirectResponse
    {
        $data = $request->validated();

        // points feeds real bonus money — only points.rules.manage overrides
        // the flat default. Anyone else's value is silently ignored, not
        // rejected, so the form still saves with the default it already shows.
        if (! $request->user()->can('updatePoints', TicketSubtask::class)) {
            unset($data['points']);
        }

        $subtask = $this->subtasks->create($ticket, $data, $request->user()->id);

        $this->notifications->notifyUser(
            $subtask->assignee_id,
            $ticket,
            'subtask.assigned',
            "اتسندت لك صب تاسك على {$ticket->ticket_number}: {$subtask->title}",
            $request->user()->id,
        );

        return back()->with('status', 'تم إضافة الصب تاسك.');
    }

    public function update(SubtaskRequest $request, Ticket $ticket, TicketSubtask $subtask): RedirectResponse
    {
        $this->assertBelongs($ticket, $subtask);

        $before = $subtask->assignee_id;

        $data = $request->validated();

        if (! $request->user()->can('updatePoints', TicketSubtask::class)) {
            unset($data['points']);
        }

        $this->subtasks->update($subtask, $data);

        if ($subtask->assignee_id !== null && $subtask->assignee_id !== $before) {
            $this->notifications->notifyUser(
                $subtask->assignee_id,
                $ticket,
                'subtask.assigned',
                "اتسندت لك صب تاسك على {$ticket->ticket_number}: {$subtask->title}",
                $request->user()->id,
            );
        }

        return back()->with('status', 'تم حفظ الصب تاسك.');
    }

    public function destroy(Request $request, Ticket $ticket, TicketSubtask $subtask): RedirectResponse
    {
        $this->assertBelongs($ticket, $subtask);
        $this->authorize('delete', $subtask);

        $this->subtasks->delete($subtask);

        return back()->with('status', 'تم حذف الصب تاسك.');
    }

    /** Drag-and-drop reorder. F08 */
    public function reorder(Request $request, Ticket $ticket): JsonResponse
    {
        $this->authorize('create', [TicketSubtask::class, $ticket]);

        $data = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer'],
        ]);

        $this->subtasks->reorder($ticket, $data['ids']);

        return response()->json(['ok' => true]);
    }

    /** Nested bindings resolve independently — this is the IDOR guard. § 5 */
    private function assertBelongs(Ticket $ticket, TicketSubtask $subtask): void
    {
        abort_unless($subtask->ticket_id === $ticket->id, 404);
    }
}
