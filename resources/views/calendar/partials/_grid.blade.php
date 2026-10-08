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

<div @class(['cal', 'cal--day' => $view === 'day']) data-calendar>
    @if ($view !== 'day')
        @foreach ($weekdays as $name)
            <div class="cal__head">{{ $name }}</div>
        @endforeach
    @endif

    @foreach ($days as $day)
        @php
            $key = $day->toDateString();
            $outsideMonth = $view === 'month' && $day->month !== $anchor->month;
            $holiday = $holidays[$key] ?? $holidays[$day->format('m-d')] ?? null;
            $isWorking = $holiday === null && in_array($day->dayOfWeek, array_map('intval', (array) \App\Models\Setting::get('work_days', [0,1,2,3,4])), true);
            $daySubtasks = $outsideMonth ? collect() : ($subtasksByDay[$key] ?? collect());
            $leavesToday = $outsideMonth ? collect() : $items['leaves']->filter(fn ($l) => $l->covers($day));
        @endphp

        <div
            @class([
                'cal__day',
                'cal__day--full' => $view === 'day',
                'cal__day--outside' => $outsideMonth,
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
                $dayTickets = $outsideMonth ? collect() : ($ticketsByDay[$key] ?? collect());
                $daySlas = $outsideMonth ? collect() : ($slasByDay[$key] ?? collect());
                $totalItems = $daySubtasks->count() + $dayTickets->count() + $daySlas->count();
                $summaryParts = collect([
                    $daySubtasks->count() ? $daySubtasks->count() . ' مهمة' : null,
                    $dayTickets->count() ? $dayTickets->count() . ' موعد' : null,
                    $daySlas->count() ? $daySlas->count() . ' SLA' : null,
                ])->filter()->implode(' · ');
                $cap = $view === 'week' ? 4 : $totalItems;
                $shown = 0;
            @endphp

            @if ($view === 'month')
                {{-- Month is an overview, not seven tiny task lists. Long mixed
                     Arabic/English titles were unreadable at this width; show
                     an honest workload summary and open the readable day view. --}}
                @if ($totalItems > 0)
                    <a class="cal__day-summary"
                       href="{{ route($routeName, array_merge(array_filter($filters), ['view' => 'day', 'date' => $key])) }}">
                        <strong>{{ $totalItems }}</strong>
                        <span>عنصر</span>
                        <small>{{ $summaryParts }}</small>
                    </a>
                @endif
            @else
                {{-- One clip region for every kind, so nothing spills past the cell. --}}
                <div data-items class="cal__body">
                    @include('calendar.partials._day-items', [
                        'daySubtasks' => $daySubtasks,
                        'dayTickets' => $dayTickets,
                        'daySlas' => $daySlas,
                        'cap' => $cap,
                        'key' => $key,
                    ])
                </div>
            @endif

            @if ($view !== 'month' && $totalItems > $cap)
                <a class="cal__more"
                   href="{{ route($routeName, array_merge(array_filter($filters), ['view' => 'day', 'date' => $key])) }}">
                    +{{ $totalItems - $cap }} أكثر
                </a>
            @endif
        </div>
    @endforeach
</div>
