@extends('layouts.app')

@section('title', 'بوردي')

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

        <div class="board">
            @foreach ($columns as $column)
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

                    <div class="board__list">
                        @forelse ($column['tickets'] as $ticket)
                            <x-ticket-card :ticket="$ticket" :user="$user" />
                        @empty
                            <p class="board__empty">فاضي</p>
                        @endforelse
                    </div>
                </section>
            @endforeach
        </div>
    </div>
@endsection
