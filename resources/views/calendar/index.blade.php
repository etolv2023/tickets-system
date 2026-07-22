@extends('layouts.app')

@section('title', $title)

@push('scripts')
    {{-- Only the calendar drags dates, so only the calendar loads this. --}}
    @vite('resources/js/features/calendar.js')
@endpush

@section('content')
    {{-- ★★★★ (2026-07-21): a month/timeline grid, same case as the board. --}}
    <div class="page page--wide">
        <div class="page__head">
            <div>
                <h1 class="page-title">{{ $title }}</h1>
                <p class="page-subtitle">
                    {{ $view === 'day'
                        ? $anchor->translatedFormat('l j F Y')
                        : $anchor->translatedFormat('F Y') }}
                </p>
            </div>
        </div>

        @include('calendar.partials._toolbar')
        @include('calendar.partials._filters')

        @if ($view === 'timeline')
            @include('calendar.partials._timeline')
        @elseif ($view === 'day')
            {{-- One full-width day — no grid to squeeze it into a column. --}}
            @include('calendar.partials._grid')
        @else
            {{-- Month/week: the grid on desktop, a readable agenda on the phone.
                 Only one is ever visible (calendar-agenda.css). --}}
            <div class="cal-grid-only">
                @include('calendar.partials._grid')
            </div>
            <div class="cal-agenda-only">
                @include('calendar.partials._agenda')
            </div>
        @endif

        @include('calendar.partials._legend')
    </div>
@endsection
