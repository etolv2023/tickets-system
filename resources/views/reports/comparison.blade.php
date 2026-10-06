@extends('layouts.app')
@section('title', 'مقارنة أداء الموظفين')
@section('content')
<div class="page page--wide reports-page">
    <div class="page__head"><div><h1 class="page-title">مقارنة أداء الموظفين</h1><p class="page-subtitle">التذكرة المسندة لأكتر من موظف بتتحسب لكل واحد فيهم.</p></div><x-export-button route="export.comparison" /></div>
    @include('reports.partials._management-filters')
    <x-card title="المقارنة" flush><div class="table-wrap"><table class="table table--hover reports-table">
        <thead><tr><th>الموظف</th><th>المسند</th><th>المحلول</th><th>المفتوح</th><th>المتأخر</th><th>الالتزام</th><th>متوسط الحل</th></tr></thead>
        <tbody>@forelse ($rows as $row)<tr>
            <td><a href="{{ route('reports.performance', request()->query() + ['user' => $row->user_id]) }}">{{ $row->name }}</a></td>
            <td class="u-nums">@if ((empty($filters['assignee']) || (int) $filters['assignee'] === $row->user_id) && (empty($filters['relation']) || $filters['relation'] === 'assigned'))<a href="{{ route('tickets.index', array_merge($drilldown, ['assignee' => $row->user_id, 'relation' => 'assigned'])) }}">{{ $row->assigned }}</a>@else{{ $row->assigned }}@endif</td>
            <td class="u-nums">{{ $row->resolved }}</td>
            <td class="u-nums">@if ((empty($filters['assignee']) || (int) $filters['assignee'] === $row->user_id) && (empty($filters['relation']) || $filters['relation'] === 'assigned'))<a href="{{ route('tickets.index', array_merge($drilldown, ['assignee' => $row->user_id, 'relation' => 'assigned', 'statuses' => $openStatusKeys])) }}">{{ $row->currently_open }}</a>@else{{ $row->currently_open }}@endif</td>
            <td class="u-nums">@if ((empty($filters['assignee']) || (int) $filters['assignee'] === $row->user_id) && (empty($filters['relation']) || $filters['relation'] === 'assigned') && (empty($filters['deadline']) || $filters['deadline'] === 'overdue'))<a href="{{ route('tickets.index', array_merge($drilldown, ['assignee' => $row->user_id, 'relation' => 'assigned', 'deadline' => 'overdue'])) }}">{{ $row->currently_overdue }}</a>@else{{ $row->currently_overdue }}@endif</td>
            <td class="u-nums">{{ $row->on_time_percentage ?? 0 }}%</td><td class="u-nums">{{ number_format($row->avg_resolution_hours ?? 0, 1) }} س</td>
        </tr>@empty<tr class="table__empty"><td colspan="7">مفيش بيانات بالفلاتر دي.</td></tr>@endforelse</tbody>
    </table></div></x-card>
</div>
@endsection
