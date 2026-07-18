@php
    /*
     * Bucketing only — every row is already loaded. Keying by "ticket|date"
     * makes each of the 7 lookups per row O(1) instead of a filter over the
     * whole week. F09
     */
    $byTicket = $entries->groupBy('ticket_id');
    $cells = $entries->groupBy(fn ($e) => $e->ticket_id . '|' . $e->spent_on->toDateString());
    $num = fn ($v) => rtrim(rtrim(number_format($v, 2), '0'), '.');
@endphp

<div class="sheet">
    <div class="sheet__row sheet__row--head">
        <div class="sheet__label">التذكرة</div>
        @foreach ($days as $day)
            <div @class(['sheet__cell', 'sheet__cell--today' => $day->isToday()])>
                {{ $day->translatedFormat('D j') }}
            </div>
        @endforeach
        <div class="sheet__cell">إجمالي</div>
    </div>

    @foreach ($byTicket as $ticketId => $rows)
        @php $ticket = $rows->first()->ticket; @endphp

        <div class="sheet__row">
            <a class="sheet__ticket" href="{{ route('tickets.show', $ticketId) }}">
                <span class="sheet__number">{{ $ticket->ticket_number }}</span>
                <span class="sheet__title">{{ $ticket->title }}</span>
            </a>

            @foreach ($days as $day)
                @php $hours = ($cells[$ticketId . '|' . $day->toDateString()] ?? collect())->sum('hours'); @endphp
                <div @class([
                    'sheet__cell',
                    'sheet__cell--empty' => $hours <= 0,
                    'sheet__cell--today' => $day->isToday(),
                ])>
                    {{ $hours > 0 ? $num($hours) : '—' }}
                </div>
            @endforeach

            <div class="sheet__cell sheet__cell--total">{{ $num($rows->sum('hours')) }}</div>
        </div>
    @endforeach

    <div class="sheet__row sheet__row--foot">
        <div class="sheet__label">إجمالي اليوم</div>

        @foreach ($days as $day)
            @php
                $hours = $byDay[$day->toDateString()] ?? 0;
                // Capacity colouring stays with the daily figure. F14
                $state = match (true) {
                    $hours <= 0 => 'empty',
                    $hours > $capacity => 'over',
                    $hours >= $capacity * 0.85 => 'near',
                    default => null,
                };
            @endphp
            <div @class([
                'sheet__cell',
                'sheet__cell--' . $state => $state,
                'sheet__cell--today' => $day->isToday(),
            ])>
                {{ $hours > 0 ? $num($hours) : '—' }}
            </div>
        @endforeach

        <div class="sheet__cell sheet__cell--total">{{ $num($total) }}</div>
    </div>
</div>

{{-- The colours in the footer row mean something; unexplained they are just
     red text on a number. --}}
<ul class="legend">
    <li class="legend__item">
        <span class="legend__key legend__key--plain"></span>
        تحت سعتك اليومية ({{ rtrim(rtrim($capacity, '0'), '.') }} ساعة)
    </li>
    <li class="legend__item">
        <span class="legend__key legend__key--near"></span>
        قربت تكمل سعة اليوم
    </li>
    <li class="legend__item">
        <span class="legend__key legend__key--over"></span>
        عدّيت سعة اليوم
    </li>
</ul>
