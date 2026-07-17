@extends('layouts.app')

@section('title', 'بورد التيم')

@section('content')
    <div class="page">
        <div class="page__head">
            <div>
                <h1 class="page-title">بورد التيم</h1>
                <p class="page-subtitle">
                    الشغل المفتوح مقسّم بالـ{{ $lane === 'priority' ? 'أولوية' : 'مسؤول' }}،
                    وآخر أسبوعين من المقفول.
                </p>
            </div>
            <div class="page__actions">
                <x-button :variant="$lane === 'assignee' ? 'secondary' : 'ghost'" size="sm"
                          :href="route('board.team', ['lane' => 'assignee'])">بالمسؤول</x-button>
                <x-button :variant="$lane === 'priority' ? 'secondary' : 'ghost'" size="sm"
                          :href="route('board.team', ['lane' => 'priority'])">بالأولوية</x-button>
            </div>
        </div>

        @if (session('status'))
            <x-alert variant="success">{{ session('status') }}</x-alert>
        @endif

        {{-- A board that quietly drops half the work is worse than no board.
             If the ceiling bit, say so and point at the screen that doesn't cap. --}}
        @if ($shown < $total)
            <x-alert>
                البورد بيعرض <strong>{{ $shown }}</strong> من <strong>{{ $total }}</strong> تذكرة —
                البورد مش مصمم لأكتر من كده.
                للصورة الكاملة استخدم <a href="{{ route('tickets.index') }}">قائمة التذاكر</a> بالفلاتر.
            </x-alert>
        @endif

        @forelse ($lanes as $laneName => $columns)
            <x-card :title="$laneName" flush>
                <x-slot:actions>
                    <span class="u-subtle">
                        {{ collect($columns)->sum(fn ($c) => $c['tickets']->count()) }} تذكرة
                    </span>
                </x-slot:actions>

                <div class="card__body">
                    <div class="board">
                        @foreach ($columns as $column)
                            <section class="board__column">
                                <header class="board__head">
                                    <h3 class="board__title">{{ $column['label'] }}</h3>
                                    <span class="board__count">
                                        {{ $column['tickets']->count() }}
                                        @if ($column['hidden'] > 0)
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
            </x-card>
        @empty
            <x-card>
                <p class="card__empty">مفيش شغل مفتوح دلوقتي.</p>
            </x-card>
        @endforelse
    </div>
@endsection
