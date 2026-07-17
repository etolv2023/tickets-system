@extends('layouts.app')

@section('title', 'لوحة الصدارة')

@section('content')
    <div class="page page--narrow">
        <div class="page__head">
            <div>
                <h1 class="page-title">لوحة الصدارة</h1>
                <p class="page-subtitle">النقاط المصروفة في الشهر المختار.</p>
            </div>
            <div class="page__actions">
                <x-button variant="secondary" :href="route('export.points', ['period' => $period])">
                    تصدير Excel
                </x-button>
            </div>
        </div>

        @include('reports.partials._month-picker', ['route' => 'reports.leaderboard'])

        <x-card flush>
            <div class="subtasks">
                @forelse ($rows as $i => $row)
                    <div class="points-row">
                        <span class="points-row__rank">{{ $i + 1 }}</span>
                        <x-avatar :user="$row->user" />
                        <div class="points-row__name">
                            @can('reports.view')
                                <a href="{{ route('reports.employee', ['user' => $row->user_id, 'period' => $period]) }}">
                                    {{ $row->user?->name ?? '—' }}
                                </a>
                            @else
                                {{ $row->user?->name ?? '—' }}
                            @endcan
                            <div class="u-subtle">{{ $row->awards }} مرة</div>
                        </div>
                        <span class="points-row__points">{{ rtrim(rtrim(number_format($row->total, 2), '0'), '.') }}</span>
                    </div>
                @empty
                    <p class="card__empty">مفيش نقاط اتصرفت في الشهر ده.</p>
                @endforelse
            </div>
        </x-card>
    </div>
@endsection
