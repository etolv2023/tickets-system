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
            <div class="page__actions">
                <x-export-button route="export.team-activity" />
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
                                <th>التسليم</th>
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
                                            @forelse ($ticket->roleAssignments as $assignment)
                                                @if ($assignment->user)<x-avatar :user="$assignment->user" size="sm" />@endif
                                            @empty
                                                <span class="u-subtle">مش موزعة</span>
                                            @endforelse
                                        </div>
                                    </td>
                                    <td class="u-nums">{{ $ticket->reported_at->translatedFormat('j M Y') }}</td>
                                    <td class="u-nums">{{ $ticket->resolved_at?->translatedFormat('j M Y') ?? '—' }}</td>
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
                                <tr class="table__empty">
                                    <td colspan="8">مفيش تذاكر بالفلاتر دي.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-card>

            {{ $tickets->links() }}
        @endif

        @include('reports.partials._team-activity-subtasks')
    </div>
@endsection
