@extends('layouts.app')
@section('title', 'أعمار التذاكر المفتوحة')
@section('content')
<div class="page page--wide reports-page">
    <div class="page__head"><div><h1 class="page-title">أعمار التذاكر المفتوحة</h1><p class="page-subtitle">مدة بقاء التذاكر المفتوحة من تاريخ البلاغ.</p></div><x-export-button route="export.aging" /></div>
    @include('reports.partials._management-filters')
    @foreach (['totals' => 'الإجمالي', 'by_assignee' => 'حسب المسؤول', 'by_type' => 'حسب النوع', 'by_priority' => 'حسب الأولوية'] as $key => $title)
        <x-card :title="$title" flush class="reports-section"><div class="table-wrap"><table class="table reports-table">
            <thead><tr><th>العمر بالأيام</th>@if ($key !== 'totals')<th>البند</th>@endif<th>مفتوحة</th><th>منها متأخرة</th></tr></thead>
            <tbody>@forelse ($report[$key] as $row)<tr><td class="u-nums">{{ $row->age_bucket }}</td>
                @if ($key !== 'totals')<td>{{ $row->name ?? $row->key }}</td>@endif
                <td class="u-nums">{{ $row->ticket_count }}</td><td class="u-nums">{{ $row->overdue_count }}</td>
            </tr>@empty<tr class="table__empty"><td colspan="4">مفيش بيانات.</td></tr>@endforelse</tbody>
        </table></div></x-card>
    @endforeach
</div>
@endsection
