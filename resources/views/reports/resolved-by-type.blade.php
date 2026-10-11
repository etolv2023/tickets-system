@extends('layouts.app')

@section('title', __('الحلول حسب النوع'))

@section('content')
    {{-- ★ (2026-10-05) F19.5 — people down, ticket types across. The question
         "حل كام بج، كام اكسبشن" asked of the whole team at once, over any range,
         and of one person with the tickets behind the numbers. --}}
    <div class="page page--wide">
        <div class="page__head">
            <div>
                <h1 class="page-title row">
                    <x-icon name="check-circle" class="u-icon-accent" />
                    {{ __('الحلول حسب النوع') }}
                </h1>
                <p class="page-subtitle">
                    {{ __('من حلّ كم تذكرة من كل نوع في هذه الفترة. «محلولة» تعني أن حالتها اليوم محلولة أو مغلقة وأن تاريخ حلها ضمن الفترة — التذكرة التي أُعيد فتحها بعد حلها لا تُحتسب.') }}
                </p>
            </div>
            <div class="page__actions">
                <x-export-button route="export.resolved-by-type" />
            </div>
        </div>

        @include('reports.partials._resolved-filters')

        {{-- The headline figures. The range itself is in the card header below,
             not in a tile: a date pair is not a number to glance at. --}}
        <div class="today-stats">
            <div class="stat-tile stat-tile--green">
                <div class="stat-tile__figure">{{ $data['total'] }}</div>
                <div class="stat-tile__caption">{{ __('تذاكر حُلّت في الفترة') }}</div>
            </div>
            @if ($data['tickets'] === null)
                <div class="stat-tile stat-tile--teal">
                    <div class="stat-tile__figure">{{ $data['rows']->count() }}</div>
                    <div class="stat-tile__caption">{{ __('أشخاص حلّوا تذاكر') }}</div>
                </div>
            @else
                <div class="stat-tile stat-tile--slate">
                    <div class="stat-tile__figure">{{ $data['avgHours'] === null ? '—' : __(':hours س', ['hours' => $data['avgHours']]) }}</div>
                    <div class="stat-tile__caption">{{ __('متوسط زمن الحل') }}</div>
                </div>
            @endif
            <div class="stat-tile stat-tile--amber">
                <div class="stat-tile__figure">{{ $data['lateCount'] }}</div>
                <div class="stat-tile__caption">{{ __('منها حُلّت بعد موعدها') }}</div>
            </div>
        </div>

        @if ($data['tickets'] === null)
            @include('reports.partials._resolved-matrix')
        @else
            @include('reports.partials._resolved-person')
        @endif
    </div>
@endsection
