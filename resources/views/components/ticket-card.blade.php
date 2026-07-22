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

    // Which columns will accept this card. The board dims the rest while it is
    // being dragged, so a refusal is visible before the drop, not after.
    $drops = \App\Http\Controllers\BoardController::droppableColumns($ticket, $user->id);
@endphp

<article class="tcard" data-ticket-id="{{ $ticket->id }}"
         data-move-url="{{ route('board.move', $ticket) }}"
         data-drop-columns="{{ implode(' ', $drops) }}">
    <x-priority-stripe :priority="$ticket->priority" />

    <div class="tcard__top">
        {{-- Only the grip starts a drag, so the title link, the status select,
             the accordion and the بدأت / خلصت buttons all still work. --}}
        <span class="tcard__grip" data-drag-handle title="اسحب لعمود تاني">
            <svg width="12" height="12" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path d="M7 4a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0zm0 6a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0zm0 6a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0zm9-12a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0zm0 6a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0zm0 6a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0z" />
            </svg>
        </span>
        <span class="tcard__number">{{ $ticket->ticket_number }}</span>
        <x-badge :variant="$ticket->type->variant()" :icon="$ticket->type->icon()">{{ $ticket->type->label() }}</x-badge>
    </div>

    <a class="tcard__title" href="{{ route('tickets.show', $ticket) }}">{{ $ticket->title }}</a>

    <div class="tcard__meta">
        <span>{{ $ticket->originLabel() }}</span>
        @if ($mine)
            <x-badge variant="neutral">{{ $mine->roleLabel() }}</x-badge>
        @endif
        <span @class(['tcard__age--overdue' => $ticket->isOverdue()])>{{ $ticket->ageLabel() }}</span>
        @if ($ticket->subtasks_total > 0)
            <span class="tickets__subtasks">{{ $ticket->subtasks_done }}/{{ $ticket->subtasks_total }}</span>
        @endif
    </div>

    {{-- No status picker on the card: dragging the card between columns is the
         board's own way of moving a ticket, and a select beside it competes
         with the drag for the same job. --}}

    {{-- Native <details>: no JavaScript to open it, keyboard-accessible, and
         collapsed by default so a board of 300 cards never lays the rows out.
         Rendered only when the subtasks relation was eager-loaded — the board
         loads it, other callers of this card may not. --}}
    @if ($ticket->subtasks_total > 0 && $ticket->relationLoaded('subtasks'))
        <details class="tcard__subtasks">
            <summary class="tcard__subtasks-summary">
                الصب تاسكس <span data-subtask-counter>{{ $ticket->subtasks_done }}/{{ $ticket->subtasks_total }}</span>
            </summary>

            <ul class="tcard__subtask-list">
                @foreach ($ticket->subtasks as $subtask)
                    <li class="tcard__subtask" data-subtask-id="{{ $subtask->id }}">
                        <span @class(['tcard__subtask-title', 'tcard__subtask-title--done' => $subtask->status->isDone()])>
                            {{ $subtask->title }}
                        </span>

                        @can('update', [$subtask, $ticket])
                            @if (! $subtask->status->needsReason())
                                <select class="select select--sm" aria-label="حالة {{ $subtask->title }}"
                                        data-subtask-status="{{ route('subtasks.status', $subtask) }}"
                                        data-current="{{ $subtask->status->value }}">
                                    @foreach (\App\Models\SubtaskStatusDefinition::quickChangeKeys() as $optionKey)
                                        <option value="{{ $optionKey }}" @selected($subtask->status->value === $optionKey)>{{ \App\Casts\SubtaskStatusValue::for($optionKey)->label() }}</option>
                                    @endforeach
                                </select>
                            @else
                                <x-badge :variant="$subtask->status->variant()">{{ $subtask->status->label() }}</x-badge>
                            @endif
                        @else
                            <x-badge :variant="$subtask->status->variant()">{{ $subtask->status->label() }}</x-badge>
                        @endcan

                        <p class="subtask__message" data-subtask-message role="status" aria-live="polite"></p>
                    </li>
                @endforeach
            </ul>
        </details>
    @endif

    {{-- "the backend is still working" — so you know whether the ball is with
         you or with someone else. F12.1 --}}
    @if ($other)
        <div class="tcard__other">
            <span class="badge__dot"></span>
            {{ $other->roleLabel() }}: {{ $other->statusLabel() }}
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
