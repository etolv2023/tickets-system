{{-- The subtasks half of /reports/team-activity. Split out when the tickets
     table grew its deadline column (CLAUDE.md § 3: 150 lines). --}}
@if ($subtasks !== null)
    <x-card :title="__('المهام الفرعية')" flush>
        <x-slot:actions>
            <span class="u-subtle">{{ $subtasks->total() }}</span>
        </x-slot:actions>

        <div class="table-wrap">
            <table class="table table--hover">
                <thead>
                    <tr>
                        <th>{{ __('المهمة الفرعية') }}</th>
                        <th>{{ __('التذكرة') }}</th>
                        <th>{{ __('الدور') }}</th>
                        <th>{{ __('الحالة') }}</th>
                        <th>{{ __('المسؤول') }}</th>
                        <th>{{ __('الاستحقاق') }}</th>
                        <th>{{ __('المقدَّر/الفعلي') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($subtasks as $subtask)
                        <tr>
                            <td>{{ $subtask->title }}</td>
                            <td>
                                <a class="u-mono u-ltr" href="{{ route('tickets.show', $subtask->ticket) }}">
                                    {{ $subtask->ticket->ticket_number }}
                                </a>
                                <span class="u-subtle">{{ $subtask->ticket->title }}</span>
                            </td>
                            <td><x-badge variant="neutral">{{ $subtask->role?->label() ?? __('عام') }}</x-badge></td>
                            <td><x-badge :variant="$subtask->status->variant()">{{ $subtask->status->label() }}</x-badge></td>
                            <td>
                                @if ($subtask->assignee)
                                    <div class="row">
                                        <x-avatar :user="$subtask->assignee" size="sm" />
                                        {{ $subtask->assignee->name }}
                                    </div>
                                @else
                                    <span class="u-subtle">{{ __('غير مُسندة') }}</span>
                                @endif
                            </td>
                            <td @class(['u-nums', 'tickets__age--overdue' => $subtask->isOverdue()])>
                                {{ $subtask->due_date?->translatedFormat('j M Y') ?? '—' }}
                                @if ($subtask->isOverdue())
                                    <x-icon name="alert" :aria-label="__('متأخرة')" />
                                @endif
                            </td>
                            <td class="u-mono u-nums">
                                @if ($subtask->estimated_hours)
                                    {{ rtrim(rtrim($subtask->spent_hours, '0'), '.') }}/{{ rtrim(rtrim($subtask->estimated_hours, '0'), '.') }} {{ __('س') }}
                                @else
                                    —
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr class="table__empty">
                            <td colspan="7">{{ __('لا توجد مهام فرعية مطابقة لعوامل التصفية.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>

    {{ $subtasks->links() }}
@endif
