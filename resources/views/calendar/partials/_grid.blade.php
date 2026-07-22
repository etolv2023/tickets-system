@php
    /*
     * Everything is already loaded; this only buckets it by day. F13
     * Keys are date strings so a lookup is O(1) per cell rather than a filter
     * over the whole collection 42 times.
     */
    $subtasksByDay = $items['subtasks']->groupBy(fn ($s) => $s->due_date->toDateString());
    $ticketsByDay = $items['tickets']->groupBy(fn ($t) => $t->due_date->toDateString());
    $slasByDay = $items['slas']->groupBy(fn ($t) => $t->sla_due_at->toDateString());
@endphp

<div class="cal" data-calendar>
    @if ($view !== 'day')
        @foreach ($weekdays as $name)
            <div class="cal__head">{{ $name }}</div>
        @endforeach
    @endif

    @foreach ($days as $day)
        @php
            $key = $day->toDateString();
            $holiday = $holidays[$key] ?? $holidays[$day->format('m-d')] ?? null;
            $isWorking = $holiday === null && in_array($day->dayOfWeek, array_map('intval', (array) \App\Models\Setting::get('work_days', [0,1,2,3,4])), true);
            $daySubtasks = $subtasksByDay[$key] ?? collect();
            $leavesToday = $items['leaves']->filter(fn ($l) => $l->covers($day));
        @endphp

        <div
            @class([
                'cal__day',
                'cal__day--full' => $view === 'day',
                'cal__day--outside' => $view === 'month' && $day->month !== $anchor->month,
                'cal__day--off' => ! $isWorking,
                'cal__day--today' => $day->isToday(),
            ])
            data-date="{{ $key }}"
        >
            <div class="cal__top">
                <span class="cal__num">{{ $day->translatedFormat($view === 'day' ? 'l j F' : 'j') }}</span>

                @if ($holiday)
                    <span class="cal__holiday" title="{{ $holiday }}">{{ Str::limit($holiday, 12) }}</span>
                @elseif (! $isTeam && isset($capacity[auth()->id() . '|' . $key]))
                    @include('calendar.partials._capacity', ['meter' => $capacity[auth()->id() . '|' . $key]])
                @endif
            </div>

            {{-- Leave first: if the person is out, everything below is a lie
                 unless you know that. F14 --}}
            @foreach ($leavesToday as $leave)
                <span class="cal__leave">
                    <x-avatar :user="$leave->user" size="sm" />
                    إجازة {{ $leave->typeLabel() }}
                </span>
            @endforeach

            @php
                /*
                 * A day with many items used to overflow its fixed-height cell
                 * and break the grid. In month/week the cell shows the first few
                 * and a "+N أكثر" link into the day view, which lists them all;
                 * the day view itself (cal__day--full) is uncapped. The counter
                 * runs across all three kinds so the cap is the whole day, not
                 * per-kind.
                 */
                $dayTickets = $ticketsByDay[$key] ?? collect();
                $daySlas = $slasByDay[$key] ?? collect();
                $totalItems = $daySubtasks->count() + $dayTickets->count() + $daySlas->count();
                $cap = in_array($view, ['month', 'week'], true) ? 4 : $totalItems;
                $shown = 0;
            @endphp

            {{-- One clip region for every kind, so nothing spills past the cell. --}}
            <div data-items class="cal__body">
                @foreach ($daySubtasks as $subtask)
                    @continue($shown >= $cap)
                    <a
                        @class([
                            'cal__item',
                            'cal__item--' . $subtask->ticket->priority->variant(),
                            'cal__item--overdue' => $subtask->isOverdue(),
                            'cal__item--done' => $subtask->status === \App\Enums\SubtaskStatus::Done,
                        ])
                        href="{{ route('tickets.show', $subtask->ticket_id) }}"
                        data-subtask-id="{{ $subtask->id }}"
                        data-due-date="{{ $key }}"
                        data-original-due="{{ $key }}"
                        draggable="true"
                        title="{{ $subtask->ticket->ticket_number }} — {{ $subtask->title }}{{ $subtask->estimated_hours ? ' (' . rtrim(rtrim($subtask->estimated_hours, '0'), '.') . ' س)' : '' }}"
                    >
                        {{-- A subtask says who and how long. A ticket deadline
                             says neither — that is the difference you are meant
                             to see without reading. --}}
                        @if ($subtask->assignee)
                            <span class="cal__who">{{ $subtask->assignee->initials() }}</span>
                        @endif
                        <span class="cal__text">{{ $subtask->title }}</span>
                        @if ($subtask->estimated_hours)
                            <span class="cal__hours u-mono">{{ rtrim(rtrim($subtask->estimated_hours, '0'), '.') }}س</span>
                        @endif
                    </a>
                    @php $shown++; @endphp
                @endforeach

                {{-- Ticket deadlines are a different kind of thing from the work
                     below them, so they get a different shape: a solid bar, not a
                     tinted chip. --}}
                @foreach ($dayTickets as $ticket)
                    @continue($shown >= $cap)
                    <a class="cal__ticket cal__ticket--{{ $ticket->priority->variant() }}"
                       href="{{ route('tickets.show', $ticket) }}"
                       title="استحقاق التذكرة — {{ $ticket->title }}">
                        <span class="cal__ticket-tag">تذكرة</span>
                        <span class="u-mono">{{ $ticket->ticket_number }}</span>
                    </a>
                    @php $shown++; @endphp
                @endforeach

                {{-- F13: an SLA reads as a red circle, never a block — it is a
                     promise to the customer, not a task you can drag. --}}
                @foreach ($daySlas as $sla)
                    @continue($shown >= $cap)
                    <a class="cal__sla" href="{{ route('tickets.show', $sla) }}"
                       title="مهلة SLA — {{ $sla->title }}">
                        <span class="cal__sla-dot"></span>
                        <span class="u-mono">{{ $sla->ticket_number }}</span>
                    </a>
                    @php $shown++; @endphp
                @endforeach
            </div>

            @if ($totalItems > $cap)
                <a class="cal__more"
                   href="{{ route($routeName, array_merge(array_filter($filters), ['view' => 'day', 'date' => $key])) }}">
                    +{{ $totalItems - $cap }} أكثر
                </a>
            @endif
        </div>
    @endforeach
</div>
