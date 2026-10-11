{{-- F09 — this person's own entries on the ticket, fetched when the time tab is
     first opened (TicketPanelController::time). Nothing at all when there are
     none, so the panel ends at the form, as it always did. --}}

@if ($myTimeEntries->isNotEmpty())
    <hr class="divider">
    <p class="u-subtle">{{ __('وقتي في هذه التذكرة') }}</p>

    <div class="facts">
        @foreach ($myTimeEntries as $entry)
            @php
                // Resolved from the ticket's subtasks, loaded once by
                // the controller — not a query per entry.
                $entrySubtask = $entry->subtask_id
                    ? $ticket->subtasks->firstWhere('id', $entry->subtask_id)
                    : null;
            @endphp
            <div class="facts__row">
                <span class="facts__label">
                    {{ $entry->spent_on->translatedFormat('j M') }}
                    @if ($entrySubtask)
                        — {{ Str::limit($entrySubtask->title, 20) }}
                    @endif
                </span>
                <span class="row">
                    <span class="facts__value facts__value--num">
                        {{ __(':hours س', ['hours' => rtrim(rtrim($entry->hours, '0'), '.')]) }}
                    </span>
                    <form method="POST" action="{{ route('time.destroy', $entry) }}">
                        @csrf
                        @method('DELETE')
                        <x-button variant="ghost" size="sm">×</x-button>
                    </form>
                </span>
            </div>
        @endforeach
    </div>
@endif
