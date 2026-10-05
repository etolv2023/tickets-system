{{-- The subtasks half of /reports/team-activity. Split out when the tickets
     table grew its deadline column (CLAUDE.md § 3: 150 lines). --}}
@if ($subtasks !== null)
    <x-card title="الصب تاسكس" flush>
        <x-slot:actions>
            <span class="u-subtle">{{ $subtasks->total() }}</span>
        </x-slot:actions>

        <div class="table-wrap">
            <table class="table table--hover">
                <thead>
                    <tr>
                        <th>الصب تاسك</th>
                        <th>التذكرة</th>
                        <th>الدور</th>
                        <th>الحالة</th>
                        <th>المسؤول</th>
                        <th>الاستحقاق</th>
                        <th>مقدّر/فعلي</th>
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
                            <td><x-badge variant="neutral">{{ $subtask->role?->name_ar ?? 'عام' }}</x-badge></td>
                            <td><x-badge :variant="$subtask->status->variant()">{{ $subtask->status->label() }}</x-badge></td>
                            <td>
                                @if ($subtask->assignee)
                                    <div class="row">
                                        <x-avatar :user="$subtask->assignee" size="sm" />
                                        {{ $subtask->assignee->name }}
                                    </div>
                                @else
                                    <span class="u-subtle">مش مسندة</span>
                                @endif
                            </td>
                            <td @class(['u-nums', 'tickets__age--overdue' => $subtask->isOverdue()])>
                                {{ $subtask->due_date?->translatedFormat('j M Y') ?? '—' }}
                                @if ($subtask->isOverdue())
                                    <x-icon name="alert" aria-label="متأخرة" />
                                @endif
                            </td>
                            <td class="u-mono u-nums">
                                @if ($subtask->estimated_hours)
                                    {{ rtrim(rtrim($subtask->spent_hours, '0'), '.') }}/{{ rtrim(rtrim($subtask->estimated_hours, '0'), '.') }} س
                                @else
                                    —
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr class="table__empty">
                            <td colspan="7">مفيش صب تاسكس بالفلاتر دي.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>

    {{ $subtasks->links() }}
@endif
