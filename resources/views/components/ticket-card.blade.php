@props(['ticket', 'user' => null])

@php
    $user ??= auth()->user();

    // The log for the side THIS user owns. A "both" developer can hold two.
    $mine = $ticket->relationLoaded('workLogs')
        ? $ticket->workLogs->firstWhere('user_id', $user->id)
        : null;

    $other = $ticket->relationLoaded('workLogs')
        ? $ticket->workLogs->first(fn ($l) => $l->user_id !== $user->id)
        : null;
@endphp

<article class="tcard">
    <x-priority-stripe :priority="$ticket->priority" />

    <div class="tcard__top">
        <span class="tcard__number">{{ $ticket->ticket_number }}</span>
        <x-badge :variant="$ticket->type->variant()">{{ $ticket->type->label() }}</x-badge>
    </div>

    <a class="tcard__title" href="{{ route('tickets.show', $ticket) }}">{{ $ticket->title }}</a>

    <div class="tcard__meta">
        <span>{{ $ticket->company->name }}</span>
        @if ($mine)
            <x-badge variant="neutral">{{ $mine->side->label() }}</x-badge>
        @endif
        <span @class(['tcard__age--overdue' => $ticket->isOverdue()])>{{ $ticket->ageLabel() }}</span>
        @if ($ticket->subtasks_total > 0)
            <span class="tickets__subtasks">{{ $ticket->subtasks_done }}/{{ $ticket->subtasks_total }}</span>
        @endif
    </div>

    {{-- "the backend is still working" — so you know whether the ball is with
         you or with someone else. F12.1 --}}
    @if ($other)
        <div class="tcard__other">
            <span class="badge__dot"></span>
            {{ $other->side->label() }}: {{ $other->statusLabel() }}
        </div>
    @endif

    @if ($mine && $mine->status !== 'done')
        {{-- One contextual button: بدأت becomes خلصت. The button is the primary
             path, not drag-and-drop. F12.1 --}}
        <div class="tcard__actions">
            @if ($mine->status === 'pending')
                <form method="POST" action="{{ route('tickets.work.start', [$ticket, $mine]) }}">
                    @csrf
                    <x-button variant="primary" size="sm">بدأت</x-button>
                </form>
            @else
                <form method="POST" action="{{ route('tickets.work.finish', [$ticket, $mine]) }}">
                    @csrf
                    <x-button variant="secondary" size="sm">خلصت</x-button>
                </form>
            @endif
        </div>
    @endif
</article>
