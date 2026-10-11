@extends('layouts.app')
@section('title', __('تقرير المواعيد وSLA'))
@section('content')
<div class="page page--wide reports-page">
    <div class="page__head"><div><h1 class="page-title">{{ __('تقرير المواعيد وSLA') }}</h1><p class="page-subtitle">{{ __('الالتزام بموعد التسليم، أو مهلة الـ SLA عند عدم وجود موعد.') }}</p></div><x-export-button route="export.deadline" /></div>
    @include('reports.partials._management-filters')
    <div class="reports-stats">
        <x-stat-tile :figure="$report['summary']->completed_before" :caption="__('اكتملت مبكرًا')" variant="green" icon="check-circle" />
        <x-stat-tile :figure="$report['summary']->completed_on" :caption="__('في يوم الموعد')" variant="teal" icon="clock" />
        <x-stat-tile :figure="$report['summary']->completed_after" :caption="__('اكتملت متأخرة')" variant="amber" icon="alert" />
        @if (empty($filters['deadline']) || $filters['deadline'] === 'overdue')
            <a href="{{ route('tickets.index', array_merge($drilldown, ['deadline' => 'overdue'])) }}"><x-stat-tile :figure="$report['summary']->currently_overdue" :caption="__('متأخرة حاليًا')" variant="red" icon="alert" /></a>
        @else
            <x-stat-tile :figure="$report['summary']->currently_overdue" :caption="__('متأخرة حاليًا')" variant="red" icon="alert" />
        @endif
        <x-stat-tile :figure="number_format($report['summary']->avg_lateness_hours ?? 0, 1)" :caption="__('متوسط التأخير')" :unit="__('س')" icon="clock" />
    </div>
    <x-card :title="__('أكثر التذاكر تأخيرًا')" flush><div class="table-wrap"><table class="table table--hover reports-table">
        <thead><tr><th>{{ __('التذكرة') }}</th><th>{{ __('العنوان') }}</th><th>{{ __('النوع') }}</th><th>{{ __('الأولوية') }}</th><th>{{ __('الموعد') }}</th><th>{{ __('ساعات التأخير') }}</th></tr></thead>
        <tbody>@forelse ($report['worst_overdue'] as $ticket)<tr><td class="u-nums"><a href="{{ route('tickets.show', $ticket->id) }}">{{ $ticket->ticket_number }}</a></td><td>{{ $ticket->title }}</td><td>{{ $ticket->type_name }}</td><td>{{ $ticket->priority_name }}</td><td class="u-nums">{{ $ticket->deadline_at }}</td><td class="u-nums">{{ number_format($ticket->overdue_hours, 1) }}</td></tr>
        @empty<tr class="table__empty"><td colspan="6">{{ __('لا توجد تذاكر متأخرة.') }}</td></tr>@endforelse</tbody>
    </table></div></x-card>
</div>
@endsection
