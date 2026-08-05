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

        // ★ (2026-08-05) …and the due date the same way, for the same reason:
        // a subtask finished after it now costs MINUS its points, so the date
        // is a money field and TicketSubtaskPolicy::schedule owns it.
        if (! $request->user()->can('schedule', TicketSubtask::class)) {
            unset($data['due_date']);
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

        // ★ (2026-08-05) Moving your own deadline is how a penalty turns back
        // into an award, so the date is dropped here exactly like points.
        if (! $request->user()->can('schedule', TicketSubtask::class)) {
            unset($data['due_date']);
        }

        // ★ (2026-08-02) Handing the subtask to someone else is its own
        // permission — it moves who F18 pays. Dropped silently rather than
        // rejected, same as points above: the form still saves everything the
        // user was allowed to change, and the owner field is hidden from them
        // anyway, so a value here means a hand-edited request.
        if (array_key_exists('assignee_id', $data)
            && (int) $data['assignee_id'] !== (int) $subtask->assignee_id
            && ! $request->user()->can('reassign', [$subtask, $ticket])) {
            // role_id goes with it: a subtask's role follows its owner
            // (SubtaskService::roleFollowsAssignee), so letting one through
            // without the other is how the two drift apart.
            unset($data['assignee_id'], $data['role_id']);
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
