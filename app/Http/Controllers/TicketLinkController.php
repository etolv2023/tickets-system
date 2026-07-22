<?php

namespace App\Http\Controllers;

use App\Casts\LinkTypeValue;
use App\Models\LinkTypeDefinition;
use App\Models\Ticket;
use App\Models\TicketLink;
use App\Notifications\NotificationEvent;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** F10 — "this blocks that", stored once and read both ways. */
class TicketLinkController extends Controller
{
    public function store(Request $request, Ticket $ticket): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('links.manage'), 403);
        $this->authorize('view', $ticket);

        // Picked from a type-ahead that searches number and title, so what
        // arrives is a key rather than a number a human retyped.
        $data = $request->validate([
            'to_ticket_id' => ['required', 'integer', 'exists:tickets,id'],
            'type' => ['required', Rule::in(array_keys(LinkTypeDefinition::map()))],
        ], [], ['to_ticket_id' => 'التذكرة', 'type' => 'نوع الربط']);

        $target = Ticket::findOrFail($data['to_ticket_id']);

        if ($target->id === $ticket->id) {
            return back()->withErrors(['to_ticket_id' => 'مينفعش تربط التذكرة بنفسها.']);
        }

        // You can only link to something you're allowed to see, or the link
        // itself leaks that the ticket exists.
        $this->authorize('view', $target);

        $exists = TicketLink::where('from_ticket_id', $ticket->id)
            ->where('to_ticket_id', $target->id)
            ->where('type', $data['type'])
            ->exists();

        if ($exists) {
            return back()->withErrors(['to_ticket_id' => 'الربط ده موجود بالفعل.']);
        }

        TicketLink::create([
            'from_ticket_id' => $ticket->id,
            'to_ticket_id' => $target->id,
            'type' => $data['type'],
            'created_by' => $request->user()->id,
        ]);

        // Both circles hear it: a link is a fact about two tickets, and the
        // other ticket's owners are exactly the people it may now block.
        $notifications = app(NotificationService::class);
        $label = LinkTypeValue::for($data['type'])->label();

        $notifications->dispatch(
            $ticket,
            NotificationEvent::TicketLinked,
            "{$ticket->ticket_number} بقت {$label} {$target->ticket_number}",
            $request->user()->id,
        );

        $notifications->dispatch(
            $target,
            NotificationEvent::TicketLinked,
            "{$target->ticket_number} اترطبت بـ{$ticket->ticket_number} ({$label})",
            $request->user()->id,
        );

        return back()->with('status', "تم الربط بـ{$target->ticket_number}.");
    }

    public function destroy(Request $request, Ticket $ticket, TicketLink $link): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('links.manage'), 403);
        $this->authorize('view', $ticket);

        // The link must touch this ticket from either end.
        abort_unless(
            $link->from_ticket_id === $ticket->id || $link->to_ticket_id === $ticket->id,
            404
        );

        $link->delete();

        return back()->with('status', 'تم فك الربط.');
    }
}
