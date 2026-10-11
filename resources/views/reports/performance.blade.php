@extends('layouts.app')
@section('title', __('أداء الموظف'))
@section('content')
<div class="page page--wide reports-page">
    <div class="page__head"><div><h1 class="page-title">{{ __('أداء الموظف') }}</h1><p class="page-subtitle">{{ __('التذكرة المسندة لأكتر من موظف بتتحسب لكل واحد فيهم.') }}</p></div><x-export-button route="export.performance" :params="['user' => $user->id]" /></div>
    @include('reports.partials._management-filters')
    <div class="reports-stats">
        @if (empty($filters['created_by']) || (int) $filters['created_by'] === $user->id)<a href="{{ route('tickets.index', array_merge($drilldown, ['created_by' => $user->id])) }}"><x-stat-tile :figure="$report['created']" :caption="__('فتحها')" icon="plus" /></a>@else<x-stat-tile :figure="$report['created']" :caption="__('فتحها')" icon="plus" />@endif
        @if ((empty($filters['assignee']) || (int) $filters['assignee'] === $user->id) && (empty($filters['relation']) || $filters['relation'] === 'assigned'))<a href="{{ route('tickets.index', array_merge($drilldown, ['assignee' => $user->id, 'relation' => 'assigned'])) }}"><x-stat-tile :figure="$report['assigned']" :caption="__('مسندة له')" icon="user" /></a>@else<x-stat-tile :figure="$report['assigned']" :caption="__('مسندة له')" icon="user" />@endif
        <x-stat-tile :figure="$report['resolved']" :caption="__('محلولة')" variant="green" icon="check-circle" />
        @if ((empty($filters['assignee']) || (int) $filters['assignee'] === $user->id) && (empty($filters['relation']) || $filters['relation'] === 'assigned'))<a href="{{ route('tickets.index', array_merge($drilldown, ['assignee' => $user->id, 'relation' => 'assigned', 'statuses' => $openStatusKeys])) }}"><x-stat-tile :figure="$report['currently_open']" :caption="__('مفتوحة حاليًا')" icon="activity" /></a>@else<x-stat-tile :figure="$report['currently_open']" :caption="__('مفتوحة حاليًا')" icon="activity" />@endif
        @if ((empty($filters['assignee']) || (int) $filters['assignee'] === $user->id) && (empty($filters['relation']) || $filters['relation'] === 'assigned') && (empty($filters['deadline']) || $filters['deadline'] === 'overdue'))
            <a href="{{ route('tickets.index', array_merge($drilldown, ['assignee' => $user->id, 'relation' => 'assigned', 'deadline' => 'overdue'])) }}"><x-stat-tile :figure="$report['currently_overdue']" :caption="__('متأخرة حاليًا')" variant="red" icon="alert" /></a>
        @else
            <x-stat-tile :figure="$report['currently_overdue']" :caption="__('متأخرة حاليًا')" variant="red" icon="alert" />
        @endif
        <x-stat-tile :figure="$report['on_time_percentage'] ?? 0" :caption="__('الالتزام بالموعد')" unit="%" variant="teal" icon="clock" />
    </div>
    @foreach (['by_type' => __('حسب النوع'), 'by_status' => __('حسب الحالة'), 'by_priority' => __('حسب الأولوية'), 'by_module' => __('حسب الموديول')] as $key => $title)
        <x-card :title="$title" flush class="reports-section">
            <div class="table-wrap"><table class="table reports-table"><thead><tr><th>{{ __('البند') }}</th><th>{{ __('العدد') }}</th></tr></thead><tbody>
            @forelse ($report[$key] as $row)<tr><td>{{ $row->name ?? $row->key }}</td><td class="u-nums">
                @if (($key !== 'by_module' || $row->key !== '—') && (empty($filters['assignee']) || (int) $filters['assignee'] === $user->id) && (empty($filters['relation']) || $filters['relation'] === 'assigned') && (empty($filters[match ($key) { 'by_type' => 'type', 'by_status' => 'status', 'by_priority' => 'priority', default => 'module' }]) || $filters[match ($key) { 'by_type' => 'type', 'by_status' => 'status', 'by_priority' => 'priority', default => 'module' }] === $row->key))
                    <a href="{{ route('tickets.index', array_merge($drilldown, ['assignee' => $user->id, 'relation' => 'assigned', match ($key) { 'by_type' => 'type', 'by_status' => 'status', 'by_priority' => 'priority', default => 'module' } => $row->key])) }}">{{ $row->ticket_count }}</a>
                @else
                    {{ $row->ticket_count }}
                @endif
            </td></tr>
            @empty<tr class="table__empty"><td colspan="2">{{ __('مفيش بيانات.') }}</td></tr>@endforelse
            </tbody></table></div>
        </x-card>
    @endforeach
</div>
@endsection
