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
                $saved = $this->attachments->attachMany(
                    $ticket,
                    $request->file('attachments'),
                    $request->user()->id,
                    $comment,
                );

                // ★ (2026-08-04) The description has done this since inline
                // images existed; the comment box never did, and that only
                // stopped mattering because the picture used to die in the
                // browser before it could get here (editor.js). With that fixed
                // the placeholder now arrives intact and PASSES the purifier —
                // it is an ordinary https URL — so leaving it alone would store
                // a live link to /attachments/pending/…, a route that does not
                // exist. A silently broken image, on the app's commonest action.
                $comment->forceFill([
                    'body' => $this->attachments->resolveInlineImages(
                        $comment->body,
                        $saved,
                        $request->input('attachment_tokens', [])
                    ),
                ])->saveQuietly();
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
