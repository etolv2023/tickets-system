@extends('layouts.app')

@section('title', __('تقرير الفريق التفصيلي'))

@section('content')
    {{-- ★★★★ (2026-07-21): raw-row filterable tables, same case as the ticket list. --}}
    <div class="page page--wide">
        <div class="page__head">
            <div>
                <h1 class="page-title">{{ __('تقرير الفريق التفصيلي') }}</h1>
                <p class="page-subtitle">{{ __('التذاكر والمهام الفرعية الفعلية — ليست أرقامًا مجمّعة، بل الصفوف نفسها.') }}</p>
            </div>
            <div class="page__actions">
                <x-export-button route="export.team-activity" />
            </div>
        </div>

        @include('reports.partials._team-activity-filters')

        @if ($tickets !== null)
            <x-card :title="__('التذاكر')" flush>
                <x-slot:actions>
                    <span class="u-subtle">{{ $tickets->total() }}</span>
                </x-slot:actions>

                <div class="table-wrap">
                    <table class="table table--hover">
                        <thead>
                            <tr>
                                <th></th>
                                <th>{{ __('التذكرة') }}</th>
                                <th>{{ __('النوع') }}</th>
                                <th>{{ __('الحالة') }}</th>
                                <th>{{ __('المسؤولين') }}</th>
                                <th>{{ __('تاريخ الفتح') }}</th>
                                <th>{{ __('تاريخ الحل') }}</th>
                                <th>{{ __('التسليم') }}</th>
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
                                                <span class="u-subtle">{{ __('غير مُسندة') }}</span>
                                            @endforelse
                                        </div>
                                    </td>
                                    <td class="u-nums">{{ $ticket->reported_at->translatedFormat('j M Y') }}</td>
                                    <td class="u-nums">{{ $ticket->resolved_at?->translatedFormat('j M Y') ?? '—' }}</td>
                                    <td class="table__cell--tight">
                                        @if ($ticket->missedDeadlines() !== [])
                                            <x-badge variant="urgent" class="badge--sm">{{ __('متأخرة') }}</x-badge>
                                        @elseif ($ticket->hasDeadline())
                                            <span class="tickets__ontime">{{ __('في الموعد') }}</span>
                                        @else
                                            <span class="u-subtle">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr class="table__empty">
                                    <td colspan="8">{{ __('لا توجد تذاكر مطابقة لعوامل التصفية.') }}</td>
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
