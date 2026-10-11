@php
    /*
     * A row per person, a column per day (F13). This is the view that answers
     * "who is over capacity this month" at a glance — the whole reason F14
     * exists.
     */
    $byPerson = $items['subtasks']->groupBy('assignee_id');
    // Bucketed once by person and day. Filtering each person's list again for
    // every one of the month's cells was person × day × subtask date
    // comparisons — two seconds on a month of two thousand subtasks.
    $byPersonDay = $items['subtasks']->groupBy(fn ($s) => $s->assignee_id . '|' . $s->due_date->toDateString());
    $shown = $users->filter(fn ($u) => $byPerson->has($u->id) || $items['leaves']->contains('user_id', $u->id));
    $workDays = array_map('intval', (array) \App\Models\Setting::get('work_days', [0, 1, 2, 3, 4]));
@endphp

<div class="timeline-grid" data-calendar>
    @forelse ($shown as $person)
        <div class="timeline-grid__row">
            <div class="timeline-grid__who">
                <x-avatar :user="$person" size="sm" />
                <span>{{ $person->name }}</span>
            </div>

            <div class="timeline-grid__days" style="--value: {{ count($days) }}">
                @foreach ($days as $day)
                    @php
                        $key = $day->toDateString();
                        $meterKey = $person->id . '|' . $key;
                        $holiday = $holidays[$key] ?? $holidays[$day->format('m-d')] ?? null;
                        $isWorking = $holiday === null && in_array($day->dayOfWeek, $workDays, true);
                        $onLeave = $items['leaves']->contains(fn ($l) => $l->user_id === $person->id && $l->covers($day));
                        $theirs = $byPersonDay[$person->id . '|' . $key] ?? collect();
                    @endphp

                    <div @class(['timeline-grid__cell', 'timeline-grid__cell--off' => ! $isWorking || $onLeave])
                         data-date="{{ $key }}"
                         title="{{ $day->translatedFormat('j M') }}{{ $holiday ? ' — ' . $holiday : '' }}{{ $onLeave ? __(' — إجازة') : '' }}">
                        <div data-items class="stack stack--tight">
                            {{-- Three chips a cell, then "+N" into that person's day:
                                 a busy day was dozens of ten-letter chips no one
                                 could read, and the cell's capacity meter already
                                 says how loaded it is. --}}
                            @foreach ($theirs->take(3) as $subtask)
                                <a @class([
                                       'cal__item',
                                       'cal__item--' . $subtask->ticket->priority->variant(),
                                       'cal__item--overdue' => $subtask->isOverdue(),
                                       'cal__item--done' => $subtask->status->isDone(),
                                   ])
                                   href="{{ route('tickets.show', $subtask->ticket_id) }}"
                                   data-subtask-id="{{ $subtask->id }}"
                                   data-due-date="{{ $key }}"
                                   {{-- Owner-only drag — see _day-items. --}}
                                   @can('update', [$subtask, $subtask->ticket]) draggable="true" @endcan
                                   title="{{ $subtask->ticket->ticket_number }} — {{ $subtask->title }}">
                                    {{ Str::limit($subtask->title, 10) }}
                                </a>
                            @endforeach

                            @if ($theirs->count() > 3)
                                <a class="cal__more"
                                   href="{{ route($routeName, array_merge(array_filter($filters), ['view' => 'day', 'date' => $key, 'assignee' => $person->id])) }}">
                                    {{ __('+:count أكثر', ['count' => $theirs->count() - 3]) }}
                                </a>
                            @endif
                        </div>

                        @isset($capacity[$meterKey])
                            @include('calendar.partials._capacity', ['meter' => $capacity[$meterKey]])
                        @endisset
                    </div>
                @endforeach
            </div>
        </div>
    @empty
        <div class="card__empty">{{ __('لا يوجد عمل مجدول في هذه الفترة.') }}</div>
    @endforelse
</div>
