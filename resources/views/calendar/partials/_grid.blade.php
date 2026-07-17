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

            <div data-items class="stack stack--tight">
                @foreach ($daySubtasks as $subtask)
                    <a
                        @class([
                            'cal__item',
                            'cal__item--' . $subtask->ticket->priority->value,
                            'cal__item--overdue' => $subtask->isOverdue(),
                            'cal__item--done' => $subtask->status === \App\Enums\SubtaskStatus::Done,
                        ])
                        href="{{ route('tickets.show', $subtask->ticket_id) }}"
                        data-subtask-id="{{ $subtask->id }}"
                        data-due-date="{{ $key }}"
                        data-original-due="{{ $key }}"
                        draggable="true"
                        title="{{ $subtask->ticket->ticket_number }} — {{ $subtask->title }}{{ $subtask->estimated_hours ? ' (' . rtrim(rtrim($subtask->estimated_hours, '0'), '.') . ' س)' : '' }}"
                    >{{ $subtask->title }}</a>
                @endforeach
            </div>

            @foreach (($ticketsByDay[$key] ?? collect()) as $ticket)
                <a class="cal__item cal__item--{{ $ticket->priority->value }}"
                   href="{{ route('tickets.show', $ticket) }}"
                   title="استحقاق التذكرة">
                    <span class="u-mono">{{ $ticket->ticket_number }}</span>
                </a>
            @endforeach

            {{-- F13: an SLA reads as a red circle, never a block — it is a
                 promise to the customer, not a task you can drag. --}}
            @foreach (($slasByDay[$key] ?? collect()) as $sla)
                <a class="cal__sla" href="{{ route('tickets.show', $sla) }}"
                   title="مهلة SLA — {{ $sla->title }}">
                    <span class="cal__sla-dot"></span>
                    <span class="u-mono">{{ $sla->ticket_number }}</span>
                </a>
            @endforeach
        </div>
    @endforeach
</div>
