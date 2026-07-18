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
                        <span class="podium__rank">{{ $i + 1 }}</span>
                        <x-avatar :user="$row->user" :size="$i === 0 ? 'lg' : null" />
                        <span class="podium__name">{{ $row->user?->name ?? '—' }}</span>
                        <span class="podium__points">{{ $points($row->total) }}</span>
                        <span class="podium__meta">{{ $row->awards }} مرة</span>
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
                        <span class="points-row__count">{{ $row->awards }} مرة</span>
                        <span class="points-row__points">{{ $points($row->total) }}</span>
                    </div>
                @empty
                    @if ($podium->isEmpty())
                        <p class="card__empty">مفيش نقاط اتصرفت في الشهر ده.</p>
                    @else
                        <p class="card__empty">مفيش حد تاني في الترتيب الشهر ده.</p>
                    @endif
                @endforelse
            </div>
        </x-card>
    </div>
@endsection
