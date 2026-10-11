<x-card :title="__('تجاوز SLA')" flush>
    <x-slot:actions>
        <span class="u-subtle">{{ trans_choice(':count تذكرة|تذكرة واحدة|تذكرتان|:count تذاكر|:count تذكرة|:count تذكرة', $breaches->count()) }}</span>
    </x-slot:actions>

    <div class="table-wrap">
        <table class="table table--hover">
            <thead>
                <tr>
                    <th></th>
                    <th>{{ __('التذكرة') }}</th>
                    <th>{{ __('المهلة كانت') }}</th>
                    <th>{{ __('الحالة') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($breaches as $ticket)
                    <tr class="tickets__row">
                        <td class="tickets__cell--stripe">
                            <x-priority-stripe :priority="$ticket->priority" />
                        </td>
                        <td>
                            <span class="tickets__number">{{ $ticket->ticket_number }}</span>
                            <a class="tickets__title" href="{{ route('tickets.show', $ticket) }}">{{ $ticket->title }}</a>
                            <span class="tickets__company">{{ $ticket->originLabel() }}</span>
                        </td>
                        <td class="tickets__age tickets__age--overdue">
                            {{ $ticket->sla_due_at->timezone(config('app.display_timezone'))->translatedFormat('j M — H:i') }}
                        </td>
                        <td><x-badge :variant="$ticket->status->variant()">{{ $ticket->status->label() }}</x-badge></td>
                    </tr>
                @empty
                    <tr class="table__empty"><td colspan="4">{{ __('لا توجد تجاوزات للمهلة في هذه الفترة.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-card>
