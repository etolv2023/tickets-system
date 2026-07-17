@extends('layouts.app')

@section('title', $title)

@push('scripts')
    {{-- Only the calendar drags dates, so only the calendar loads this. --}}
    @vite('resources/js/features/calendar.js')
@endpush

@section('content')
    <div class="page">
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
        @else
            @include('calendar.partials._grid')
        @endif

        @include('calendar.partials._legend')
    </div>
@endsection
