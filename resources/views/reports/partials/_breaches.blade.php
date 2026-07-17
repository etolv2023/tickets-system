<x-card title="خرق الـ SLA" flush>
    <x-slot:actions>
        <span class="u-subtle">{{ $breaches->count() }} تذكرة</span>
    </x-slot:actions>

    <div class="table-wrap">
        <table class="table table--hover">
            <thead>
                <tr>
                    <th></th>
                    <th>التذكرة</th>
                    <th>المهلة كانت</th>
                    <th>الحالة</th>
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
                            <span class="tickets__company">{{ $ticket->company->name }}</span>
                        </td>
                        <td class="tickets__age tickets__age--overdue">
                            {{ $ticket->sla_due_at->timezone(config('app.display_timezone'))->translatedFormat('j M — H:i') }}
                        </td>
                        <td><x-badge :variant="$ticket->status->variant()">{{ $ticket->status->label() }}</x-badge></td>
                    </tr>
                @empty
                    <tr class="table__empty"><td colspan="4">مفيش خرق للمهلة في الفترة دي.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-card>
