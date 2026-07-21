@extends('layouts.app')

@section('title', 'اليوم')

@section('content')
    {{-- ★★★★★ (2026-07-21) redesign — "الشريط الحي" / Live Spine. Three zones
         with a real reading order: a header band that fuses the greeting with a
         live pulse-strip of the four action counts (small tinting chips, NOT a
         second row of big tiles); the four action questions as a masonry that
         never leaves a column half-empty; and a labelled overview zone whose
         empty widgets collapse to a one-line "all clear" strip instead of a big
         card full of air (CLAUDE.md § 6). Read-only over the same DashboardService
         queries — no business logic changed. --}}
    <div class="page page--wide">
        @include('home.partials._hero')

        @if (session('status'))
            <x-alert variant="success">{{ session('status') }}</x-alert>
        @endif

        @include('home.partials._action-sections')

        @include('home.partials._overview')
    </div>
@endsection
