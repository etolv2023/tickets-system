@extends('layouts.app')

@section('title', 'التذاكر')

@section('content')
    <div class="page">
        <div class="page__head">
            <div>
                <h1 class="page-title">التذاكر</h1>
                <p class="page-subtitle">{{ $tickets->total() }} تذكرة · مرتبة بالأولوية ثم الأقدم أولاً.</p>
            </div>
            @can('create', App\Models\Ticket::class)
                <div class="page__actions">
                    <x-button variant="primary" :href="route('tickets.create')">افتح تذكرة</x-button>
                </div>
            @endcan
        </div>

        @if (session('status'))
            <x-alert variant="success">{{ session('status') }}</x-alert>
        @endif

        @include('tickets.partials._filters')

        <x-card flush>
            <div class="table-wrap">
                <table class="table table--hover">
                    <thead>
                        <tr>
                            <th></th>
                            <th>التذكرة</th>
                            <th>النوع</th>
                            <th>الحالة</th>
                            <th>المسؤولين</th>
                            <th>{{ ($filters['status'] ?? '') === 'resolved' ? 'زمن الحل' : 'العمر' }}</th>
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
                                    <span class="tickets__company">{{ $ticket->company->name }}</span>
                                </td>
                                <td>
                                    <x-badge :variant="$ticket->type->variant()">{{ $ticket->type->label() }}</x-badge>
                                </td>
                                <td>
                                    <x-badge :variant="$ticket->status->variant()">{{ $ticket->status->label() }}</x-badge>
                                </td>
                                <td>
                                    <div class="tickets__people">
                                        @if ($ticket->frontend)
                                            <x-avatar :user="$ticket->frontend" size="sm" />
                                        @endif
                                        @if ($ticket->backend)
                                            <x-avatar :user="$ticket->backend" size="sm" />
                                        @endif
                                        @unless ($ticket->frontend || $ticket->backend)
                                            <span class="u-subtle">مش موزعة</span>
                                        @endunless
                                    </div>
                                </td>
                                <td @class(['tickets__age', 'tickets__age--overdue' => $ticket->isOverdue()])>
                                    {{ $ticket->ageLabel() }}
                                    @if ($ticket->isOverdue())
                                        <span title="عدّى مهلة الـ SLA">⚠</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr class="table__empty">
                                <td colspan="6">
                                    {{ array_filter($filters) ? 'مفيش تذاكر بالفلاتر دي.' : 'مفيش تذاكر لسه.' }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-card>

        {{ $tickets->links() }}
    </div>
@endsection
