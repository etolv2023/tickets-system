<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** F11 — follow / unfollow. */
class TicketWatcherController extends Controller
{
    public function toggle(Request $request, Ticket $ticket): RedirectResponse
    {
        $this->authorize('view', $ticket);

        $result = $ticket->watchers()->toggle($request->user()->id);

        return back()->with(
            'status',
            $result['attached'] !== [] ? 'بقيت بتتابع التذكرة دي.' : 'بطّلت متابعة التذكرة دي.'
        );
    }
}
