<?php

namespace App\Http\Controllers;

use App\Enums\LinkType;
use App\Models\Ticket;
use App\Models\TicketLink;
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

        $data = $request->validate([
            // Linked by ticket number, because that's the handle a human has.
            'ticket_number' => ['required', 'string', 'exists:tickets,ticket_number'],
            'type' => ['required', Rule::enum(LinkType::class)],
        ], [], ['ticket_number' => 'رقم التذكرة', 'type' => 'نوع الربط']);

        $target = Ticket::where('ticket_number', $data['ticket_number'])->firstOrFail();

        if ($target->id === $ticket->id) {
            return back()->withErrors(['ticket_number' => 'مينفعش تربط التذكرة بنفسها.']);
        }

        // You can only link to something you're allowed to see, or the link
        // itself leaks that the ticket exists.
        $this->authorize('view', $target);

        $exists = TicketLink::where('from_ticket_id', $ticket->id)
            ->where('to_ticket_id', $target->id)
            ->where('type', $data['type'])
            ->exists();

        if ($exists) {
            return back()->withErrors(['ticket_number' => 'الربط ده موجود بالفعل.']);
        }

        TicketLink::create([
            'from_ticket_id' => $ticket->id,
            'to_ticket_id' => $target->id,
            'type' => $data['type'],
            'created_by' => $request->user()->id,
        ]);

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
