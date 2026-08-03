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

    {{-- The ticket's first picture, so a board of bug reports can be read by
         eye. Full-bleed to the top and end edges but stopping at the priority
         stripe, which still runs the whole height — the stripe is how priority
         is read, and a banner across it would cut that signal in half.

         The id row that follows RIDES the bottom of this image (see
         `.tcard__hero + .tcard__top` in board-card.css), which is why the two
         are siblings rather than nested: a card with no picture must come out
         byte-for-byte what it was, and an adjacent-sibling rule is the only
         version of that which cannot leak.

         The thumbnail, never the original: AttachmentService writes a 320px
         copy at upload, and a board is the last place to send a 4MB photo.
         Rendered only when the relation was eager-loaded, like the subtask
         accordion below — a caller that didn't load it gets a card with no
         cover rather than a query per card. --}}
    @if ($ticket->relationLoaded('coverImage') && $ticket->coverImage)
        <div class="tcard__hero">
            <img src="{{ route('attachments.thumb', $ticket->coverImage) }}"
                 alt="" loading="lazy" decoding="async">
        </div>
    @endif

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
