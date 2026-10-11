@extends('layouts.app')

@section('title', __('لوحة الصدارة'))

@section('content')
    <div class="page">
        <div class="page__head">
            <div>
                <h1 class="page-title row">
                    <x-icon name="trophy" class="u-icon-accent" />
                    {{ __('لوحة الصدارة') }}
                </h1>
                <p class="page-subtitle">{{ __('النقاط المحتسبة في الشهر المحدد.') }}</p>
            </div>
            <div class="page__actions">
                <x-export-button route="export.points" :params="['period' => $period]" />
            </div>
        </div>

        @if ($rows->isNotEmpty())
            <div class="today-stats">
                <div class="stat-tile stat-tile--teal">
                    <div class="stat-tile__figure">{{ rtrim(rtrim(number_format($rows->sum('total'), 2), '0'), '.') }}</div>
                    <div class="stat-tile__caption">{{ __('إجمالي النقاط الموزّعة') }}</div>
                </div>
                <div class="stat-tile stat-tile--slate">
                    <div class="stat-tile__figure">{{ $rows->count() }}</div>
                    <div class="stat-tile__caption">{{ __('موظفون حصلوا على نقاط') }}</div>
                </div>
                <div class="stat-tile stat-tile--amber">
                    <div class="stat-tile__figure">{{ $rows->sum('awards') }}</div>
                    <div class="stat-tile__caption">{{ __('مرات احتساب النقاط') }}</div>
                </div>
            </div>
        @endif

        <form method="GET" action="{{ route('reports.leaderboard') }}" class="filters">
            <select name="period" class="select filters__select" aria-label="{{ __('الشهر') }}">
                @foreach ($months as $value => $label)
                    <option value="{{ $value }}" @selected($period === $value)>{{ __($label) }}</option>
                @endforeach
            </select>

            <div class="filters__combobox">
                <x-combobox name="person" resource="users"
                            :value="$filters['person'] ?? null"
                            :selected="$selectedPerson"
                            :placeholder="__('جميع الموظفين')" />
            </div>

            <div class="filters__combobox">
                <x-combobox name="assignee" resource="users"
                            :value="$filters['assignee'] ?? null"
                            :selected="$selectedAssignee"
                            :placeholder="__('جميع المسؤولين')" />
            </div>

            <x-button variant="secondary" size="sm">{{ __('تصفية') }}</x-button>

            @if (array_filter($filters))
                <a class="btn btn--ghost btn--sm" href="{{ route('reports.leaderboard', ['period' => $period]) }}">{{ __('مسح') }}</a>
            @endif
        </form>

        @php
            // Display order only: second, first, third — so the winner stands
            // in the middle of the podium.
            $ranked = $rows->values();
            $podium = collect([1, 0, 2])->map(fn ($i) => [$i, $ranked[$i] ?? null])->filter(fn ($p) => $p[1]);
            $rest = $ranked->slice(3)->values();
            $points = fn ($v) => rtrim(rtrim(number_format($v, 2), '0'), '.');
        @endphp

        @if ($podium->isNotEmpty())
            <div class="podium">
                @foreach ($podium as [$i, $row])
                    <div @class(['podium__place', 'podium__place--first' => $i === 0])>
                        @if ($i === 0)
                            <x-icon name="trophy" class="podium__trophy" />
                        @else
                            <span class="podium__rank">{{ $i + 1 }}</span>
                        @endif
                        <x-avatar :user="$row->user" :size="$i === 0 ? 'lg' : null" />
                        <span class="podium__name">{{ $row->user?->name ?? '—' }}</span>
                        <span class="podium__points">{{ $points($row->total) }}</span>
                        <span class="podium__meta">{{ trans_choice(':count مرة|مرة واحدة|مرتان|:count مرات|:count مرة|:count مرة', $row->awards) }}</span>
                    </div>
                @endforeach
            </div>
        @endif

        <x-card flush>
            <div class="subtasks">
                @forelse ($rest as $i => $row)
                    <div class="points-row">
                        <span class="points-row__rank">{{ $i + 4 }}</span>
                        <x-avatar :user="$row->user" />
                        <div class="points-row__name">
                            @can('reports.view')
                                <a href="{{ route('reports.employee', ['user' => $row->user_id, 'period' => $period]) }}">
                                    {{ $row->user?->name ?? '—' }}
                                </a>
                            @else
                                {{ $row->user?->name ?? '—' }}
                            @endcan
                        </div>
                        <span class="points-row__count">{{ trans_choice(':count مرة|مرة واحدة|مرتان|:count مرات|:count مرة|:count مرة', $row->awards) }}</span>
                        <span class="points-row__points">{{ $points($row->total) }}</span>
                    </div>
                @empty
                    @if ($podium->isEmpty())
                        <p class="card__empty">{{ __('لم تُحتسب نقاط هذا الشهر.') }}</p>
                    @else
                        <p class="card__empty">{{ __('لا يوجد آخرون في الترتيب هذا الشهر.') }}</p>
                    @endif
                @endforelse
            </div>
        </x-card>
    </div>
@endsection
