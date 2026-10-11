@extends('layouts.app')

@section('title', __('ui.nav.team_board'))

@push('scripts')
    {{-- Dragging cards between columns, plus the subtask controls the cards
         carry. board.js imports what it needs from subtasks.js. --}}
    @vite('resources/js/features/board.js')
@endpush

@section('content')
    {{-- ★★★★ (2026-07-20): a board is a grid, not prose — layout.css calls
         this out by name as the case .page--wide exists for. --}}
    <div class="page page--wide">
        <div class="page__head">
            <div>
                <h1 class="page-title">{{ __('ui.nav.team_board') }}</h1>
                <p class="page-subtitle">{{ __('ui.board.team_intro', ['lane' => $lane === 'priority' ? __('ui.board.lane_priority') : __('ui.board.lane_assignee'), 'month' => $months[$period]]) }}</p>
            </div>
            <div class="page__actions">
                <x-button :variant="$lane === 'assignee' ? 'secondary' : 'ghost'" size="sm"
                          :href="route('board.team', ['lane' => 'assignee'])">{{ __('ui.board.by_assignee') }}</x-button>
                <x-button :variant="$lane === 'priority' ? 'secondary' : 'ghost'" size="sm"
                          :href="route('board.team', ['lane' => 'priority'])">{{ __('ui.board.by_priority') }}</x-button>
            </div>
        </div>

        @include('board.partials._filters')

        @if (session('status'))
            <x-alert variant="success">{{ session('status') }}</x-alert>
        @endif

        {{-- A status change made from a card can be refused; the message has
             to land somewhere. --}}
        @if ($errors->any())
            <x-alert variant="error">{{ $errors->first() }}</x-alert>
        @endif

        {{-- A board that quietly drops half the work is worse than no board.
             If the ceiling bit, say so and point at the screen that doesn't cap. --}}
        @if ($shown < $total)
            <x-alert>
                {{ __('ui.board.limit', ['shown' => $shown, 'total' => $total]) }}
            </x-alert>
        @endif

        {{-- Where a refused drop reports itself. --}}
        <p class="board__message" data-board-message role="status" aria-live="polite"></p>

        @forelse ($lanes as $swimlane)
            <x-card flush>
                <x-slot:title>
                    <span class="board__lane">
                        @if ($swimlane['user'])
                            <x-avatar :user="$swimlane['user']" size="sm" />
                        @endif
                        {{ $swimlane['label'] }}
                    </span>
                </x-slot:title>

                <x-slot:actions>
                    <span class="u-subtle">
                        {{ trans_choice(':count تذكرة|تذكرة واحدة|تذكرتان|:count تذاكر|:count تذكرة|:count تذكرة', collect($swimlane['columns'])->sum(fn ($c) => $c['tickets']->count())) }}
                    </span>
                </x-slot:actions>

                <div class="card__body">
                    <div class="board">
                        @foreach ($swimlane['columns'] as $key => $column)
                            <section class="board__column">
                                <header class="board__head">
                                    <h3 class="board__title">{{ $column['label'] }}</h3>
                                    <span class="board__count">
                                        {{ $column['tickets']->count() }}
                                        @if ($column['hidden'] > 0)
                                            <span title="{{ __('ui.board.more_hidden', ['count' => $column['hidden']]) }}">+{{ $column['hidden'] }}</span>
                                        @endif
                                    </span>
                                </header>

                                <div class="board__list" data-board-list="{{ $key }}"
                                     data-board-group="lane-{{ $loop->parent->index }}">
                                    @forelse ($column['tickets'] as $ticket)
                                        <x-ticket-card :ticket="$ticket" :user="$user" />
                                    @empty
                                        <p class="board__empty">{{ __('فارغ') }}</p>
                                    @endforelse
                                </div>
                            </section>
                        @endforeach
                    </div>
                </div>
            </x-card>
        @empty
            <x-card>
                <p class="card__empty">{{ __('ui.board.empty_team') }}</p>
            </x-card>
        @endforelse
    </div>
@endsection
