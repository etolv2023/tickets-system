@extends('layouts.app')

@section('title', 'بوردي')

@push('scripts')
    {{-- Dragging cards between columns, plus the subtask controls the cards
         carry. board.js imports what it needs from subtasks.js. --}}
    @vite('resources/js/features/board.js')
@endpush

@section('content')
    <div class="page">
        <div class="page__head">
            <div>
                <h1 class="page-title">بوردي</h1>
                <p class="page-subtitle">
                    الشغل المسند ليك انت بس. عمود «مغلقة» بيوري آخر أسبوعين —
                    الباقي في <a href="{{ route('tickets.index') }}">التذاكر</a>.
                </p>
            </div>
        </div>

        @if (session('status'))
            <x-alert variant="success">{{ session('status') }}</x-alert>
        @endif

        @if ($errors->any())
            <x-alert variant="error">{{ $errors->first() }}</x-alert>
        @endif

        @php
            /* Nothing assigned at all reads very differently from "this one
               column happens to be empty" — and four dashed boxes cannot say
               the difference. */
            $isEmpty = collect($columns)->every(fn ($c) => $c['tickets']->isEmpty());
        @endphp

        @if ($isEmpty)
            <x-card>
                <div class="board__blank">
                    <p class="board__blank-title">مفيش حاجة مسندة ليك دلوقتي.</p>
                    <p>أول ما تتسند ليك تذكرة هتلاقيها هنا في عمود «مسندة إليّ».</p>
                </div>
            </x-card>
        @else
            {{-- Where a refused drop reports itself. --}}
            <p class="board__message" data-board-message role="status" aria-live="polite"></p>

            <div class="board board--standalone">
                @foreach ($columns as $key => $column)
                    <section class="board__column">
                        <header class="board__head">
                            <h2 class="board__title">{{ $column['label'] }}</h2>
                            <span class="board__count">
                                {{ $column['tickets']->count() }}
                                @if ($column['hidden'] > 0)
                                    {{-- Never hide a cap silently. --}}
                                    <span title="فيه {{ $column['hidden'] }} كمان مش معروضين">+{{ $column['hidden'] }}</span>
                                @endif
                            </span>
                        </header>

                        <div class="board__list" data-board-list="{{ $key }}" data-board-group="board">
                            @forelse ($column['tickets'] as $ticket)
                                <x-ticket-card :ticket="$ticket" :user="$user" />
                            @empty
                                <p class="board__empty">فاضي</p>
                            @endforelse
                        </div>
                    </section>
                @endforeach
            </div>
        @endif
    </div>
@endsection
