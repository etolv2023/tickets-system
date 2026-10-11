@extends('layouts.app')

@section('title', __('سجل وقتي'))

@section('content')
    {{-- ★★★★ (2026-07-21): the timesheet is ticket × 7 days — explicitly the
         wide-table case CLAUDE.md's mobile section calls out by name. --}}
    <div class="page page--wide">
        <div class="page__head">
            <div>
                <h1 class="page-title">{{ __('سجل وقتي') }}</h1>
                <p class="page-subtitle">
                    {{ $from->translatedFormat('j M') }} — {{ $to->translatedFormat('j M Y') }}
                </p>
            </div>
            <div class="page__actions">
                <span class="timesheet__total">{{ __(':hours ساعة', ['hours' => rtrim(rtrim(number_format($total, 2), '0'), '.')]) }}</span>
                <x-export-button route="export.timesheet" />
            </div>
        </div>

        {{-- The screen was a bare grid of dashes with nothing saying what it
             was for or where the numbers come from. One sentence fixes that,
             and it is the same sentence whether the week is full or empty. --}}
        <p class="page-lead">
            {{ __('الساعات التي سجّلتها خلال أسبوع: كل صف تذكرة، وكل عمود يوم، وكل خانة ساعاتك على تلك التذكرة في ذلك اليوم. للعرض فقط — يُسجَّل الوقت من داخل التذكرة.') }}
        </p>

        @if (session('status'))
            <x-alert variant="success">{{ session('status') }}</x-alert>
        @endif

        <div class="timesheet__nav">
            <x-button variant="ghost" :href="route('time.timesheet', ['week' => $from->subWeek()->toDateString()])">
                {{ __('→ الأسبوع الماضي') }}
            </x-button>
            <x-button variant="ghost" :href="route('time.timesheet')">{{ __('هذا الأسبوع') }}</x-button>
            <x-button variant="ghost" :href="route('time.timesheet', ['week' => $from->addWeek()->toDateString()])">
                {{ __('الأسبوع القادم ←') }}
            </x-button>
        </div>

        @if ($entries->isEmpty())
            {{-- An empty week used to show an empty matrix and an empty table
                 below it — two ways of saying nothing, and neither told you
                 how to make it say something. --}}
            <x-card>
                <div class="blank">
                    <h2 class="blank__title">{{ __('لم تسجّل وقتًا هذا الأسبوع') }}</h2>
                    <p class="blank__body">
                        {{ __('يُسجَّل الوقت من صفحة التذكرة نفسها: افتح التذكرة، وتحت المهمة الفرعية التي تعمل عليها سجّل عدد الساعات واليوم الذي عملت فيه. فور التسجيل سيظهر هنا في الأسبوع الذي يقع فيه التاريخ.') }}
                    </p>
                    <div class="blank__actions">
                        <x-button variant="primary" :href="route('board.mine')">{{ __('فتح لوحتي') }}</x-button>
                        <x-button variant="ghost" :href="route('tickets.index')">{{ __('جميع التذاكر') }}</x-button>
                    </div>
                </div>
            </x-card>
        @else
            @include('time.partials._sheet')
            @include('time.partials._entries')
        @endif
    </div>
@endsection
