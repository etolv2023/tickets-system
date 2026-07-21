@extends('layouts.app')

@section('title', 'ورقة وقتي')

@section('content')
    {{-- ★★★★ (2026-07-21): the timesheet is ticket × 7 days — explicitly the
         wide-table case CLAUDE.md's mobile section calls out by name. --}}
    <div class="page page--wide">
        <div class="page__head">
            <div>
                <h1 class="page-title">ورقة وقتي</h1>
                <p class="page-subtitle">
                    {{ $from->translatedFormat('j M') }} — {{ $to->translatedFormat('j M Y') }}
                </p>
            </div>
            <div class="page__actions">
                <span class="timesheet__total">{{ rtrim(rtrim(number_format($total, 2), '0'), '.') }} ساعة</span>
            </div>
        </div>

        {{-- The screen was a bare grid of dashes with nothing saying what it
             was for or where the numbers come from. One sentence fixes that,
             and it is the same sentence whether the week is full or empty. --}}
        <p class="page-lead">
            الساعات اللي سجّلتها خلال أسبوع واحد: كل صف تذكرة، وكل عمود يوم، والخانة هي ساعاتك على
            التذكرة دي في اليوم ده. العرض بس — الوقت نفسه بتسجّله من جوه التذكرة.
        </p>

        @if (session('status'))
            <x-alert variant="success">{{ session('status') }}</x-alert>
        @endif

        <div class="timesheet__nav">
            <x-button variant="ghost" :href="route('time.timesheet', ['week' => $from->subWeek()->toDateString()])">
                ← الأسبوع الي فات
            </x-button>
            <x-button variant="ghost" :href="route('time.timesheet')">الأسبوع ده</x-button>
            <x-button variant="ghost" :href="route('time.timesheet', ['week' => $from->addWeek()->toDateString()])">
                الأسبوع الجاي →
            </x-button>
        </div>

        @if ($entries->isEmpty())
            {{-- An empty week used to show an empty matrix and an empty table
                 below it — two ways of saying nothing, and neither told you
                 how to make it say something. --}}
            <x-card>
                <div class="blank">
                    <h2 class="blank__title">مسجّلتش وقت في الأسبوع ده</h2>
                    <p class="blank__body">
                        الوقت بيتسجل من صفحة التذكرة نفسها: تفتح التذكرة، وتحت الصب تاسك اللي شغال
                        عليها تسجّل عدد الساعات واليوم اللي شتغلته فيه. أول ما تسجّل، هيظهر هنا في
                        الأسبوع اللي التاريخ بتاعه فيه.
                    </p>
                    <div class="blank__actions">
                        <x-button variant="primary" :href="route('board.mine')">افتح بوردي</x-button>
                        <x-button variant="ghost" :href="route('tickets.index')">كل التذاكر</x-button>
                    </div>
                </div>
            </x-card>
        @else
            @include('time.partials._sheet')
            @include('time.partials._entries')
        @endif
    </div>
@endsection
