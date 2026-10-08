@php
    /*
     * Everything is already loaded; this only buckets it by day. F13
     * Keys are date strings so a lookup is O(1) per cell rather than a filter
     * over the whole collection 42 times.
     */
    $subtasksByDay = $items['subtasks']->groupBy(fn ($s) => $s->due_date->toDateString());
    $ticketsByDay = $items['tickets']->groupBy(fn ($t) => $t->due_date->toDateString());
    $slasByDay = $items['slas']->groupBy(fn ($t) => $t->sla_due_at->toDateString());

    $previewDays = collect($days)->mapWithKeys(function ($day) use ($subtasksByDay, $ticketsByDay, $slasByDay) {
        $key = $day->toDateString();
        $rows = collect();

        foreach ($subtasksByDay[$key] ?? [] as $subtask) {
            $rows->push([
                'kind' => 'subtask',
                'kindLabel' => 'صب تاسك',
                'title' => $subtask->title,
                'ticketNumber' => $subtask->ticket->ticket_number,
                'ticketTitle' => $subtask->ticket->title,
                'url' => route('tickets.show', $subtask->ticket_id),
                'assignee' => $subtask->assignee?->name,
                'hours' => $subtask->estimated_hours,
                'overdue' => $subtask->isOverdue(),
            ]);
        }

        foreach ($ticketsByDay[$key] ?? [] as $ticket) {
            $rows->push([
                'kind' => 'ticket',
                'kindLabel' => 'موعد تذكرة',
                'title' => $ticket->title,
                'ticketNumber' => $ticket->ticket_number,
                'ticketTitle' => $ticket->title,
                'url' => route('tickets.show', $ticket),
                'assignee' => null,
                'hours' => null,
                'overdue' => $ticket->isOverdue(),
            ]);
        }

        foreach ($slasByDay[$key] ?? [] as $ticket) {
            $rows->push([
                'kind' => 'sla',
                'kindLabel' => 'مهلة SLA',
                'title' => $ticket->title,
                'ticketNumber' => $ticket->ticket_number,
                'ticketTitle' => $ticket->title,
                'url' => route('tickets.show', $ticket),
                'assignee' => null,
                'hours' => null,
                'overdue' => $ticket->isOverdue(),
            ]);
        }

        return [$key => $rows->values()->all()];
    });
@endphp

<div x-data="calendarDayPreview(@js($previewDays))" @keydown.escape.window="close()">
<div @class([
    'cal',
    'cal--day' => $view === 'day',
    'cal--week' => $view === 'week',
]) data-calendar>
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

            @if (in_array($view, ['month', 'week'], true))
                {{-- Month and week are overviews, not seven tiny task lists.
                     Long mixed Arabic/English titles are unreadable at this
                     width; show an honest workload summary and open the modal
                     for the readable list and ticket detail. --}}
                @if ($totalItems > 0)
                    <button type="button" class="cal__day-summary"
                            @click="openDay('{{ $key }}', '{{ $day->translatedFormat('l j F') }}')">
                        <strong>{{ $totalItems }}</strong>
                        <span>عنصر</span>
                        <small>{{ $summaryParts }}</small>
                    </button>
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

            @if ($view === 'day' && $totalItems > $cap)
                <a class="cal__more"
                   href="{{ route($routeName, array_merge(array_filter($filters), ['view' => 'day', 'date' => $key])) }}">
                    +{{ $totalItems - $cap }} أكثر
                </a>
            @endif
        </div>
    @endforeach
</div>

<div class="cal-preview" x-show="open" x-cloak role="dialog" aria-modal="true" :aria-label="`تفاصيل ${label}`">
    <button type="button" class="cal-preview__backdrop" @click="close()" aria-label="إغلاق"></button>
    <section class="cal-preview__panel">
        <header class="cal-preview__head">
            <div class="cal-preview__heading">
                <button type="button" class="btn btn--ghost btn--icon" x-show="selected" @click="back()" aria-label="الرجوع للقائمة">
                    <x-icon name="chevron-right" />
                </button>
                <div>
                    <strong x-text="selected ? selected.ticketNumber : label"></strong>
                    <span x-text="selected ? selected.kindLabel : `${items.length} عنصر`"></span>
                </div>
            </div>
            <div class="row">
                <a class="btn btn--primary btn--sm" x-show="selected" :href="selected?.url">فتح التذكرة كاملة</a>
                <a class="btn btn--secondary btn--sm"
                   :href="'{{ route($routeName) }}?view=day&date=' + date">فتح اليوم في صفحة</a>
                <button type="button" class="btn btn--ghost btn--icon" @click="close()" aria-label="إغلاق">
                    <x-icon name="close" />
                </button>
            </div>
        </header>

        <div class="cal-preview__body" x-show="!selected">
            <template x-for="(item, index) in items" :key="`${item.kind}-${item.ticketNumber}-${index}`">
                <button type="button" class="cal-preview__item" @click="choose(item)">
                    <span class="cal-preview__kind" :class="`cal-preview__kind--${item.kind}`" x-text="item.kindLabel"></span>
                    <span class="cal-preview__item-main">
                        <strong x-text="item.title"></strong>
                        <small><span x-text="item.ticketNumber"></span><template x-if="item.assignee"><span> · <span x-text="item.assignee"></span></span></template></small>
                    </span>
                    <x-icon name="chevron-left" />
                </button>
            </template>
        </div>

        <div class="cal-preview__detail" x-show="selected" x-cloak>
            <span class="cal-preview__kind" :class="`cal-preview__kind--${selected?.kind}`" x-text="selected?.kindLabel"></span>
            <h2 x-text="selected?.title"></h2>
            <p x-text="selected?.ticketTitle"></p>
            <dl>
                <template x-if="selected?.assignee"><div><dt>المسؤول</dt><dd x-text="selected.assignee"></dd></div></template>
                <template x-if="selected?.hours"><div><dt>الوقت المتوقع</dt><dd><span x-text="selected.hours"></span> ساعة</dd></div></template>
                <div><dt>الحالة الزمنية</dt><dd x-text="selected?.overdue ? 'متأخرة' : 'في الموعد'"></dd></div>
            </dl>
        </div>
    </section>
</div>
</div>
