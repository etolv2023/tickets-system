@php
    /*
     * The mobile face of the month/week views (< 60rem). A 7-column grid can't
     * be read on a phone — the chips overlap and truncate to nothing — so below
     * the breakpoint we drop the grid entirely and render a vertical agenda:
     * one full-width section per day that actually has something on it, with its
     * items listed in full and uncapped. The user scrolls; nothing is crammed.
     *
     * Desktop keeps the grid (_grid). Only one of the two is visible at a time
     * (calendar-agenda.css), so this never shows next to the grid.
     *
     * Same bucketing as _grid — cheap, everything is already loaded. F13/F14
     */
    $subtasksByDay = $items['subtasks']->groupBy(fn ($s) => $s->due_date->toDateString());
    $ticketsByDay = $items['tickets']->groupBy(fn ($t) => $t->due_date->toDateString());
    $slasByDay = $items['slas']->groupBy(fn ($t) => $t->sla_due_at->toDateString());
@endphp

<div class="cal-agenda">
    @php $hasAny = false; @endphp

    @foreach ($days as $day)
        @php
            $key = $day->toDateString();
            // Outside-month days are padding in the grid; an agenda for "July"
            // has no room for June's leftovers.
            $outside = $view === 'month' && $day->month !== $anchor->month;
        @endphp
        @continue($outside)

        @php
            $holiday = $holidays[$key] ?? $holidays[$day->format('m-d')] ?? null;
            $daySubtasks = $subtasksByDay[$key] ?? collect();
            $dayTickets = $ticketsByDay[$key] ?? collect();
            $daySlas = $slasByDay[$key] ?? collect();
            $leavesToday = $items['leaves']->filter(fn ($l) => $l->covers($day));
            $total = $daySubtasks->count() + $dayTickets->count() + $daySlas->count();
        @endphp

        {{-- A day with nothing on it is silence, not a row. --}}
        @continue($total === 0 && ! $holiday && $leavesToday->isEmpty())
        @php $hasAny = true; @endphp

        <section @class(['cal-agenda__day', 'cal-agenda__day--today' => $day->isToday()]) data-date="{{ $key }}">
            <header class="cal-agenda__date">
                <span class="cal-agenda__day-name">{{ $day->translatedFormat('l j F') }}</span>
                @if ($holiday)
                    <span class="cal-agenda__holiday">{{ $holiday }}</span>
                @elseif ($total > 0)
                    <span class="cal-agenda__count u-mono">{{ $total }}</span>
                @endif
            </header>

            <div data-items class="cal-agenda__items">
                {{-- Leave first: if the person is out, everything below is a lie
                     unless you know that. F14 --}}
                @foreach ($leavesToday as $leave)
                    <span class="cal__leave">
                        <x-avatar :user="$leave->user" size="sm" />
                        إجازة {{ $leave->typeLabel() }}
                    </span>
                @endforeach

                @include('calendar.partials._day-items', [
                    'daySubtasks' => $daySubtasks,
                    'dayTickets' => $dayTickets,
                    'daySlas' => $daySlas,
                    'cap' => $total,
                    'key' => $key,
                ])
            </div>
        </section>
    @endforeach

    @unless ($hasAny)
        <p class="cal-agenda__empty">مفيش شغل مجدول في الفترة دي.</p>
    @endunless
</div>
