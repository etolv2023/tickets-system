@props(['ticket'])

@php
    // Both maps are Cache::rememberForever, so rendering this once per row on a
    // 50-row list costs zero queries — which is the whole reason it can live in
    // a table cell and on a board card, not just on the ticket page.
    $next = \App\Models\TicketStatusDefinition::transitionMap()[$ticket->status->value] ?? [];
    $names = \App\Models\TicketStatusDefinition::options();

    // "جاري العمل" and "تم التطوير" are derived from ticket_work_logs, so they
    // belong to the بدأت / خلصت buttons, not to a picker. Offering them here
    // let the badge and the work logs drift apart.
    $next = array_values(array_diff($next, \App\Services\TicketWorkflowService::COMPUTED_STATUSES));
@endphp

@can('changeStatus', $ticket)
    @if ($next !== [])
        {{-- The shortcut: move the status and nothing else. Naming who the
             ticket is waiting on, or attaching a note, still goes through the
             full "غيّر الحالة" panel on the ticket page. --}}
        <form method="POST" action="{{ route('tickets.status.change', $ticket) }}" class="status-select">
            @csrf
            <select name="to_status" class="select select--sm" aria-label="غيّر حالة {{ $ticket->ticket_number }}"
                    x-data @change="$el.value && $el.form.submit()">
                <option value="">{{ $ticket->status->label() }}</option>
                @foreach ($next as $key)
                    <option value="{{ $key }}">{{ $names[$key] ?? $key }}</option>
                @endforeach
            </select>
        </form>
    @else
        <x-badge :variant="$ticket->status->variant()">{{ $ticket->status->label() }}</x-badge>
    @endif
@else
    <x-badge :variant="$ticket->status->variant()">{{ $ticket->status->label() }}</x-badge>
@endcan
