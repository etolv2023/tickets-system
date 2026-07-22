@extends('layouts.app')

@section('title', 'ملف ' . $employee->name)

@section('content')
    <div class="page page--medium">
        <div class="page__head">
            <div class="row">
                <x-avatar :user="$employee" size="xl" />
                <div>
                    <h1 class="page-title">{{ $employee->name }}</h1>
                    <p class="page-subtitle">
                        {{ $employee->role->name_ar }}
                    </p>
                </div>
            </div>
            <div class="page__actions">
                <span class="points-total">{{ rtrim(rtrim(number_format($data['pointsTotal'], 2), '0'), '.') }}</span>
                <span class="u-subtle">نقطة</span>
            </div>
        </div>

        @include('reports.partials._month-picker', ['route' => 'reports.employee'])

        <div class="form-grid">
            <x-card title="إيه الي عمله" flush>
                <div class="facts card__body">
                    @foreach (\App\Models\TicketTypeDefinition::map() as $key => $def)
                        @if ($key !== 'undefined')
                            @php $type = \App\Casts\TicketTypeValue::for($key); @endphp
                            <div class="facts__row">
                                <span class="facts__label">
                                    <x-badge :variant="$type->variant()" :icon="$type->icon()">{{ $type->label() }}</x-badge>
                                </span>
                                <span class="facts__value facts__value--num">
                                    {{ $data['byType'][$key] ?? 0 }}
                                </span>
                            </div>
                        @endif
                    @endforeach
                </div>
            </x-card>

            <x-card title="النقاط بالتفصيل" flush>
                <div class="facts card__body">
                    @forelse ($data['points'] as $row)
                        <div class="facts__row">
                            <span class="facts__label">{{ $row->side?->label() ?? $row->role?->name_ar ?? '—' }}</span>
                            <span class="facts__value facts__value--num">
                                {{ rtrim(rtrim(number_format($row->total, 2), '0'), '.') }}
                            </span>
                        </div>
                    @empty
                        <p class="u-subtle">مفيش نقاط في الشهر ده.</p>
                    @endforelse
                </div>
            </x-card>

            <x-card title="مقاييس الأداء" flush>
                <div class="facts card__body">
                    <div class="facts__row">
                        <span class="facts__label">متوسط التقييم</span>
                        <span class="facts__value facts__value--num">
                            {{ $data['avgRating'] ? number_format($data['avgRating'], 1) . '/10' : '—' }}
                        </span>
                    </div>
                    <div class="facts__row">
                        <span class="facts__label">متوسط زمن الحل</span>
                        <span class="facts__value facts__value--num">
                            {{ $data['avgResolutionHours'] ? $data['avgResolutionHours'] . ' ساعة' : '—' }}
                        </span>
                    </div>
                    <div class="facts__row">
                        <span class="facts__label">
                            دقة التقدير
                            <small class="u-subtle">مقدّر ÷ فعلي</small>
                        </span>
                        <span class="facts__value facts__value--num">
                            {{ $data['estimateAccuracy'] ?? '—' }}
                        </span>
                    </div>
                    <div class="facts__row">
                        <span class="facts__label">الساعات المسجّلة</span>
                        <span class="facts__value facts__value--num">
                            {{ rtrim(rtrim(number_format($data['hoursLogged'], 2), '0'), '.') }}
                        </span>
                    </div>
                    <div class="facts__row">
                        <span class="facts__label">معدل الارتجاع</span>
                        <span @class([
                            'facts__value', 'facts__value--num',
                            'facts__value--overdue' => $data['reopenRate']['rate'] > 20,
                        ])>
                            {{ $data['reopenRate']['rate'] }}%
                            <small class="u-subtle">
                                ({{ $data['reopenRate']['reopened'] }} من {{ $data['reopenRate']['resolved'] }})
                            </small>
                        </span>
                    </div>
                </div>
            </x-card>
        </div>

        <x-card title="التذاكر الي شارك فيها" flush>
            <div class="table-wrap">
                <table class="table table--hover">
                    <thead>
                        <tr>
                            <th></th>
                            <th>التذكرة</th>
                            <th>النوع</th>
                            <th>الحالة</th>
                            <th>زمن الحل</th>
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
                                <td class="tickets__age">{{ $ticket->ageLabel() }}</td>
                            </tr>
                        @empty
                            <tr class="table__empty">
                                <td colspan="5">مفيش تذاكر اتحلت في الشهر ده.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-card>
    </div>
@endsection
