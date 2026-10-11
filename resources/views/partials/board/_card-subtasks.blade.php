{{-- A board card's subtask rows. Fetched when the card's accordion is first
     opened (BoardController::subtasks) rather than rendered with the board:
     on a full board this list was most of the page's render time and
     megabytes of HTML for rows nobody had opened. --}}
@foreach ($ticket->subtasks as $subtask)
    <li class="tcard__subtask" data-subtask-id="{{ $subtask->id }}">
        <span @class(['tcard__subtask-title', 'tcard__subtask-title--done' => $subtask->status->isDone()])>
            {{ $subtask->title }}
        </span>

        @can('update', [$subtask, $ticket])
            @if (! $subtask->status->needsReason())
                <select class="select select--sm" aria-label="{{ __('حالة :name', ['name' => $subtask->title]) }}"
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
