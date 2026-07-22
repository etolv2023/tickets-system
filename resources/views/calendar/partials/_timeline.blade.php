@php
    /*
     * A row per person, a column per day (F13). This is the view that answers
     * "who is over capacity this month" at a glance — the whole reason F14
     * exists.
     */
    $byPerson = $items['subtasks']->groupBy('assignee_id');
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
                        $theirs = ($byPerson[$person->id] ?? collect())->filter(fn ($s) => $s->due_date->toDateString() === $key);
                    @endphp

                    <div @class(['timeline-grid__cell', 'timeline-grid__cell--off' => ! $isWorking || $onLeave])
                         data-date="{{ $key }}"
                         title="{{ $day->translatedFormat('j M') }}{{ $holiday ? ' — ' . $holiday : '' }}{{ $onLeave ? ' — إجازة' : '' }}">
                        <div data-items class="stack stack--tight">
                            @foreach ($theirs as $subtask)
                                <a @class([
                                       'cal__item',
                                       'cal__item--' . $subtask->ticket->priority->variant(),
                                       'cal__item--overdue' => $subtask->isOverdue(),
                                       'cal__item--done' => $subtask->status->isDone(),
                                   ])
                                   href="{{ route('tickets.show', $subtask->ticket_id) }}"
                                   data-subtask-id="{{ $subtask->id }}"
                                   data-due-date="{{ $key }}"
                                   draggable="true"
                                   title="{{ $subtask->ticket->ticket_number }} — {{ $subtask->title }}">
                                    {{ Str::limit($subtask->title, 10) }}
                                </a>
                            @endforeach
                        </div>

                        @isset($capacity[$meterKey])
                            @include('calendar.partials._capacity', ['meter' => $capacity[$meterKey]])
                        @endisset
                    </div>
                @endforeach
            </div>
        </div>
    @empty
        <div class="card__empty">مفيش شغل مجدول في الفترة دي.</div>
    @endforelse
</div>
