@extends('layouts.app')

@section('title', 'تقرير التيم التفصيلي')

@section('content')
    {{-- ★★★★ (2026-07-21): raw-row filterable tables, same case as the ticket list. --}}
    <div class="page page--wide">
        <div class="page__head">
            <div>
                <h1 class="page-title">تقرير التيم التفصيلي</h1>
                <p class="page-subtitle">التذاكر والصب تاسكس الفعلية — مش أرقام مجمّعة، الصفوف نفسها.</p>
            </div>
        </div>

        @include('reports.partials._team-activity-filters')

        @if ($tickets !== null)
            <x-card title="التذاكر" flush>
                <x-slot:actions>
                    <span class="u-subtle">{{ $tickets->total() }}</span>
                </x-slot:actions>

                <div class="table-wrap">
                    <table class="table table--hover">
                        <thead>
                            <tr>
                                <th></th>
                                <th>التذكرة</th>
                                <th>النوع</th>
                                <th>الحالة</th>
                                <th>المسؤولين</th>
                                <th>تاريخ الفتح</th>
                                <th>تاريخ الحل</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($tickets as $ticket)
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
                                    <td>
                                        <div class="tickets__people">
                                            @if ($ticket->frontend)<x-avatar :user="$ticket->frontend" size="sm" />@endif
                                            @if ($ticket->backend)<x-avatar :user="$ticket->backend" size="sm" />@endif
                                            @unless ($ticket->frontend || $ticket->backend)
                                                <span class="u-subtle">مش موزعة</span>
                                            @endunless
                                        </div>
                                    </td>
                                    <td class="u-nums">{{ $ticket->reported_at->translatedFormat('j M Y') }}</td>
                                    <td class="u-nums">{{ $ticket->resolved_at?->translatedFormat('j M Y') ?? '—' }}</td>
                                </tr>
                            @empty
                                <tr class="table__empty">
                                    <td colspan="7">مفيش تذاكر بالفلاتر دي.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-card>

            {{ $tickets->links() }}
        @endif

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
                                <th>الجهة</th>
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
                                    <td><x-badge variant="neutral">{{ $subtask->side->label() }}</x-badge></td>
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
    </div>
@endsection
