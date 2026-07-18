<?php

namespace App\Http\Controllers;

use App\Http\Requests\Tickets\StoreCommentRequest;
use App\Models\Ticket;
use App\Notifications\NotificationEvent;
use App\Services\AttachmentService;
use App\Services\NotificationService;
use App\Services\TicketService;
use Illuminate\Http\RedirectResponse;
use RuntimeException;

class TicketCommentController extends Controller
{
    public function __construct(
        private readonly TicketService $tickets,
        private readonly AttachmentService $attachments,
        private readonly NotificationService $notifications,
    ) {
    }

    public function store(StoreCommentRequest $request, Ticket $ticket): RedirectResponse
    {
        $comment = $this->tickets->addComment(
            $ticket,
            $request->validated('body'),
            $request->user()->id,
            $request->boolean('is_internal'),
        );

        if ($request->hasFile('attachments')) {
            try {
                $this->attachments->attachMany(
                    $ticket,
                    $request->file('attachments'),
                    $request->user()->id,
                    $comment,
                );
            } catch (RuntimeException $e) {
                return redirect()->route('tickets.show', $ticket)
                    ->withErrors(['attachments' => $e->getMessage()]);
            }
        }

        // The commonest action on a ticket, and until now the one nobody was
        // told about. Internal or not, the circle is only ever colleagues.
        $this->notifications->dispatch(
            $ticket,
            NotificationEvent::CommentAdded,
            ($comment->is_internal ? 'ملاحظة داخلية' : 'تعليق جديد')
                . " من {$request->user()->name} على {$ticket->ticket_number}",
            $request->user()->id,
        );

        return redirect()->route('tickets.show', [$ticket, '#comment-' . $comment->id])
            ->with('status', 'تم إضافة التعليق.');
    }
}
