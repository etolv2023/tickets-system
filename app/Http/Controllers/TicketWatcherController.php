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
            $result['attached'] !== [] ? __('بقيت بتتابع التذكرة دي.') : __('بطّلت متابعة التذكرة دي.')
        );
    }
}
