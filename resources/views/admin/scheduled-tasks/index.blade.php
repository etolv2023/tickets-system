@extends('layouts.app')

@section('title', __('المهام المجدولة'))

@section('content')
    <div class="page">
        <div class="page__head">
            <div>
                <h1 class="page-title">{{ __('المهام المجدولة') }}</h1>
                <p class="page-subtitle">
                    {{ __('المهام التي تعمل تلقائيًا. يمكنك تغيير موعدها أو إيقافها أو تشغيلها مرة الآن.') }}
                    <br>
                    {{ __('قائمة المهام نفسها موجودة في الكود — هذه الشاشة تتحكم في') }} <strong>{{ __('المواعيد فقط') }}</strong>{{ __('، ولا تضيف أوامر.') }}
                </p>
            </div>
        </div>

        @if (session('status'))
            <x-alert variant="success">{{ session('status') }}</x-alert>
        @endif

        <x-form-errors />

        {{-- The failure this screen would otherwise hide, and the likeliest one:
             a server whose system cron was never installed shows every task with
             a schedule and a next-run time, and runs none of them. Nothing on
             the page looks wrong. This is what makes it look wrong. --}}
        @unless ($cronIsAlive)
            <x-alert variant="error">
                <strong>{{ __('مهمة cron على الخادم لا تعمل.') }}</strong>
                {{ __('المواعيد أدناه لا تُنفَّذ —') }}
                @if ($lastHeartbeat)
                    {{ __('آخر نبضة كانت') }}
                    {{ $lastHeartbeat->timezone(config('app.display_timezone'))->translatedFormat('j M — H:i') }}.
                @else
                    {{ __('ولم تُسجَّل أي نبضة على الإطلاق.') }}
                @endif
                {{ __('يجب وجود سطر cron على الخادم يستدعي') }}
                <span class="u-mono u-ltr">php artisan schedule:run</span> {{ __('كل دقيقة.') }}
            </x-alert>
        @endunless

        @if ($unseeded !== [])
            <div class="note">
                {{ __('توجد مهام في الكود بلا صفوف، لذا لا تعمل ولا تظهر أدناه:') }}
                <span class="u-mono u-ltr">{{ implode(__('، '), $unseeded) }}</span> {{ __('— شغّل') }} <span class="u-mono u-ltr">php artisan db:seed --class=ScheduledTaskSeeder --force</span>.
            </div>
        @endif

        <div class="stack">
            @foreach ($tasks as $task)
                @include('admin.scheduled-tasks.partials._task', ['task' => $task])
            @endforeach
        </div>

        <div class="note">
            <span class="u-mono u-ltr">php artisan schedule:audit</span> {{ __('يقارن هذه الصفوف بالقائمة الموجودة في الكود ·') }}
            <span class="u-mono u-ltr">php artisan schedule:list</span> {{ __('يعرض ما سجّله Laravel فعلًا.') }}
        </div>
    </div>
@endsection
