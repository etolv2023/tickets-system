@extends('layouts.app')
@section('title', 'تقرير المواعيد والـ SLA')
@section('content')
<div class="page page--wide reports-page">
    <div class="page__head"><div><h1 class="page-title">تقرير المواعيد والـ SLA</h1><p class="page-subtitle">الالتزام بموعد التسليم، أو مهلة الـ SLA عند عدم وجود موعد.</p></div><x-export-button route="export.deadline" /></div>
    @include('reports.partials._management-filters')
    <div class="reports-stats">
        <x-stat-tile :figure="$report['summary']->completed_before" caption="اكتملت بدري" variant="green" icon="check-circle" />
        <x-stat-tile :figure="$report['summary']->completed_on" caption="في يوم الموعد" variant="teal" icon="clock" />
        <x-stat-tile :figure="$report['summary']->completed_after" caption="اكتملت متأخر" variant="amber" icon="alert" />
        @if (empty($filters['deadline']) || $filters['deadline'] === 'overdue')
            <a href="{{ route('tickets.index', array_merge($drilldown, ['deadline' => 'overdue'])) }}"><x-stat-tile :figure="$report['summary']->currently_overdue" caption="متأخرة حاليًا" variant="red" icon="alert" /></a>
        @else
            <x-stat-tile :figure="$report['summary']->currently_overdue" caption="متأخرة حاليًا" variant="red" icon="alert" />
        @endif
        <x-stat-tile :figure="number_format($report['summary']->avg_lateness_hours ?? 0, 1)" caption="متوسط التأخير" unit="س" icon="clock" />
    </div>
    <x-card title="أكثر التذاكر تأخيرًا" flush><div class="table-wrap"><table class="table table--hover reports-table">
        <thead><tr><th>التذكرة</th><th>العنوان</th><th>النوع</th><th>الأولوية</th><th>الموعد</th><th>ساعات التأخير</th></tr></thead>
        <tbody>@forelse ($report['worst_overdue'] as $ticket)<tr><td class="u-nums"><a href="{{ route('tickets.show', $ticket->id) }}">{{ $ticket->ticket_number }}</a></td><td>{{ $ticket->title }}</td><td>{{ $ticket->type }}</td><td>{{ $ticket->priority }}</td><td class="u-nums">{{ $ticket->deadline_at }}</td><td class="u-nums">{{ number_format($ticket->overdue_hours, 1) }}</td></tr>
        @empty<tr class="table__empty"><td colspan="6">مفيش تذاكر متأخرة.</td></tr>@endforelse</tbody>
    </table></div></x-card>
</div>
@endsection
