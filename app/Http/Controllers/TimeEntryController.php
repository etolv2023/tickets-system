<?php

namespace App\Http\Controllers;

use App\Http\Requests\Tickets\TimeEntryRequest;
use App\Models\Ticket;
use App\Models\TimeEntry;
use App\Notifications\NotificationEvent;
use App\Services\NotificationService;
use App\Services\TimeTrackingService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TimeEntryController extends Controller
{
    public function __construct(
        private readonly TimeTrackingService $time,
        private readonly NotificationService $notifications,
    )
    {
    }

    public function store(TimeEntryRequest $request, Ticket $ticket): RedirectResponse
    {
        $entry = $this->time->log($ticket, $request->validated(), $request->user()->id);

        $this->notifications->dispatch(
            $ticket,
            NotificationEvent::TimeLogged,
            "{$request->user()->name} سجّل {$entry->hours} ساعة على {$ticket->ticket_number}",
            $request->user()->id,
        );

        return back()->with('status', 'تم تسجيل الوقت.');
    }

    public function destroy(Request $request, TimeEntry $entry): RedirectResponse
    {
        // Your own entries only. Someone else's hours aren't yours to erase.
        abort_unless($entry->user_id === $request->user()->id, 403, 'ده مش وقتك انت.');

        $this->time->delete($entry);

        return back()->with('status', 'تم حذف التسجيل.');
    }

    /** F09 — /my-timesheet */
    public function timesheet(Request $request): View
    {
        abort_unless($request->user()->hasPermission('time.log'), 403);

        // Week start comes from settings, so the sheet lines up with the
        // calendar the team actually uses. F13/F22.2
        $weekStart = (int) \App\Models\Setting::get('week_start_day', 6);

        $anchor = $request->query('week')
            ? CarbonImmutable::parse($request->query('week'))
            : CarbonImmutable::now();

        $from = $anchor->startOfWeek($weekStart);
        $to = $from->addDays(6);

        $week = $this->time->weekFor($request->user()->id, $from->toDateString(), $to->toDateString());

        return view('time.timesheet', [
            'from' => $from,
            'to' => $to,
            'days' => collect(range(0, 6))->map(fn (int $i) => $from->addDays($i)),
            'entries' => $week['entries'],
            'byDay' => $week['byDay'],
            'total' => $week['total'],
            'capacity' => (float) $request->user()->daily_capacity_hours,
        ]);
    }
}
