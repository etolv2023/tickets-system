{{-- One person: the counts per type, then the tickets behind them. --}}
<div class="form-grid">
    <x-card title="إيه الي حلّه" flush>
        <x-slot:actions>
            <span class="u-subtle">{{ $selectedPerson }} · {{ \App\Models\Ticket::RELATIONS[$data['relation']] }} · من <span class="u-nums">{{ $filters['from'] }}</span> إلى <span class="u-nums">{{ $filters['to'] }}</span></span>
        </x-slot:actions>

        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>النوع</th>
                        <th class="table__cell--num">اتحلت</th>
                        <th class="table__cell--num">متوسط زمن الحل</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($data['types'] as $key => $label)
                        @php($type = \App\Casts\TicketTypeValue::for($key))
                        @php($cell = $data['byType'][$key] ?? null)
                        <tr>
                            <td><x-badge :variant="$type->variant()" :icon="$type->icon()">{{ $label }}</x-badge></td>
                            <td @class(['table__cell--num', 'matrix__zero' => $cell === null])>{{ $cell->n ?? 0 }}</td>
                            <td class="table__cell--num">{{ $cell ? round((float) $cell->avg_hours) . ' س' : '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="matrix__foot">
                        <td>الإجمالي</td>
                        <td class="table__cell--num">{{ $data['total'] }}</td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </x-card>
</div>

<x-card title="التذاكر الي اتحلت" flush>
    <x-slot:actions>
        <span class="u-subtle">{{ $data['tickets']->total() }}</span>
    </x-slot:actions>

    <div class="table-wrap">
        <table class="table table--hover">
            <thead>
                <tr>
                    <th></th>
                    <th>التذكرة</th>
                    <th>النوع</th>
                    <th>الحالة</th>
                    <th>تاريخ الحل</th>
                    <th>زمن الحل</th>
                    <th>التسليم</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($data['tickets'] as $ticket)
                    <tr class="tickets__row">
                        <td class="tickets__cell--stripe">
                            <x-priority-stripe :priority="$ticket->priority" />
                        </td>
                        <td>
                            <span class="tickets__number">{{ $ticket->ticket_number }}</span>
                            <a class="tickets__title" href="{{ route('tickets.show', $ticket) }}">{{ $ticket->title }}</a>
                            <span class="tickets__company">{{ $ticket->originLabel() }}</span>
                        </td>
                        <td><x-badge :variant="$ticket->type->variant()" :icon="$ticket->type->icon()">{{ $ticket->type->label() }}</x-badge></td>
                        <td><x-badge :variant="$ticket->status->variant()">{{ $ticket->status->label() }}</x-badge></td>
                        <td class="u-nums">{{ $ticket->resolved_at?->translatedFormat('j M Y') ?? '—' }}</td>
                        <td class="tickets__age">{{ $ticket->ageLabel() }}</td>
                        <td class="table__cell--tight">
                            @if ($ticket->missedDeadlines() !== [])
                                <x-badge variant="urgent" class="badge--sm">متأخرة</x-badge>
                            @elseif ($ticket->hasDeadline())
                                <span class="tickets__ontime">في معادها</span>
                            @else
                                <span class="u-subtle">—</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr class="table__empty"><td colspan="7">مفيش تذاكر اتحلت بالفلاتر دي.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-card>

{{ $data['tickets']->links() }}
