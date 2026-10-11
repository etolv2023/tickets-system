{{-- One person: the counts per type, then the tickets behind them. --}}
<div class="form-grid">
    <x-card :title="__('إيه الي حلّه')" flush>
        <x-slot:actions>
            <span class="u-subtle">{{ $selectedPerson }} · {{ __(\App\Models\Ticket::RELATIONS[$data['relation']]) }} · {{ __('من') }} <span class="u-nums">{{ $filters['from'] }}</span> {{ __('إلى') }} <span class="u-nums">{{ $filters['to'] }}</span></span>
        </x-slot:actions>

        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>{{ __('النوع') }}</th>
                        <th class="table__cell--num">{{ __('اتحلت') }}</th>
                        <th class="table__cell--num">{{ __('متوسط زمن الحل') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($data['types'] as $key => $label)
                        @php($type = \App\Casts\TicketTypeValue::for($key))
                        @php($cell = $data['byType'][$key] ?? null)
                        <tr>
                            <td><x-badge :variant="$type->variant()" :icon="$type->icon()">{{ __($label) }}</x-badge></td>
                            <td @class(['table__cell--num', 'matrix__zero' => $cell === null])>{{ $cell->n ?? 0 }}</td>
                            <td class="table__cell--num">{{ $cell ? __(':hours س', ['hours' => round((float) $cell->avg_hours)]) : '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="matrix__foot">
                        <td>{{ __('الإجمالي') }}</td>
                        <td class="table__cell--num">{{ $data['total'] }}</td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </x-card>
</div>

<x-card :title="__('التذاكر الي اتحلت')" flush>
    <x-slot:actions>
        <span class="u-subtle">{{ $data['tickets']->total() }}</span>
    </x-slot:actions>

    <div class="table-wrap">
        <table class="table table--hover">
            <thead>
                <tr>
                    <th></th>
                    <th>{{ __('التذكرة') }}</th>
                    <th>{{ __('النوع') }}</th>
                    <th>{{ __('الحالة') }}</th>
                    <th>{{ __('تاريخ الحل') }}</th>
                    <th>{{ __('زمن الحل') }}</th>
                    <th>{{ __('التسليم') }}</th>
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
                                <x-badge variant="urgent" class="badge--sm">{{ __('متأخرة') }}</x-badge>
                            @elseif ($ticket->hasDeadline())
                                <span class="tickets__ontime">{{ __('في معادها') }}</span>
                            @else
                                <span class="u-subtle">—</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr class="table__empty"><td colspan="7">{{ __('مفيش تذاكر اتحلت بالفلاتر دي.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-card>

{{ $data['tickets']->links() }}
