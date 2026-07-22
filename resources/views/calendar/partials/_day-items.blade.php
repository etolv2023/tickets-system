@php
    /*
     * The chips for one day — subtasks, ticket deadlines, SLA markers — shared
     * by the desktop grid cell (_grid, capped at 4) and the mobile agenda row
     * (_agenda, uncapped). Extracted so the two layouts render the exact same
     * item markup from one place.
     *
     * Expects: $daySubtasks, $dayTickets, $daySlas, $cap, $key.
     * $shown runs across all three kinds so the cap is the whole day, not
     * per-kind.
     */
    $shown = 0;
@endphp

@foreach ($daySubtasks as $subtask)
    @continue($shown >= $cap)
    <a
        @class([
            'cal__item',
            'cal__item--' . $subtask->ticket->priority->variant(),
            'cal__item--overdue' => $subtask->isOverdue(),
            'cal__item--done' => $subtask->status->isDone(),
        ])
        href="{{ route('tickets.show', $subtask->ticket_id) }}"
        data-subtask-id="{{ $subtask->id }}"
        data-due-date="{{ $key }}"
        data-original-due="{{ $key }}"
        draggable="true"
        title="{{ $subtask->ticket->ticket_number }} — {{ $subtask->title }}{{ $subtask->estimated_hours ? ' (' . rtrim(rtrim($subtask->estimated_hours, '0'), '.') . ' س)' : '' }}"
    >
        {{-- A subtask says who and how long. A ticket deadline says neither —
             that is the difference you are meant to see without reading. --}}
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

{{-- Ticket deadlines are a different kind of thing from the work below them, so
     they get a different shape: a solid bar, not a tinted chip. --}}
@foreach ($dayTickets as $ticket)
    @continue($shown >= $cap)
    <a class="cal__ticket cal__ticket--{{ $ticket->priority->variant() }}"
       href="{{ route('tickets.show', $ticket) }}"
       title="استحقاق التذكرة — {{ $ticket->title }}">
        <span class="cal__ticket-tag">تذكرة</span>
        <span class="cal__text u-mono">{{ $ticket->ticket_number }}</span>
    </a>
    @php $shown++; @endphp
@endforeach

{{-- F13: an SLA reads as a red circle, never a block — it is a promise to the
     customer, not a task you can drag. --}}
@foreach ($daySlas as $sla)
    @continue($shown >= $cap)
    <a class="cal__sla" href="{{ route('tickets.show', $sla) }}"
       title="مهلة SLA — {{ $sla->title }}">
        <span class="cal__sla-dot"></span>
        <span class="cal__text u-mono">{{ $sla->ticket_number }}</span>
    </a>
    @php $shown++; @endphp
@endforeach
