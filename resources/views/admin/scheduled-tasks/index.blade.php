@extends('layouts.app')

@section('title', __('المهام المجدولة'))

@section('content')
    <div class="page">
        <div class="page__head">
            <div>
                <h1 class="page-title">{{ __('المهام المجدولة') }}</h1>
                <p class="page-subtitle">
                    {{ __('المهام اللي بتشتغل لوحدها. تقدر تغيّر ميعادها، توقفها، أو تشغّلها مرة دلوقتي.') }}
                    <br>
                    {{ __('قايمة المهام نفسها في الكود — الشاشة دي بتتحكم في') }} <strong>{{ __('المواعيد بس') }}</strong>{{ __('، مش بتضيف أوامر.') }}
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
                <strong>{{ __('الكرون بتاع السيرفر مش شغال.') }}</strong>
                {{ __('المواعيد اللي تحت دي مش بتتنفّذ —') }}
                @if ($lastHeartbeat)
                    {{ __('آخر نبضة كانت') }}
                    {{ $lastHeartbeat->timezone(config('app.display_timezone'))->translatedFormat('j M — H:i') }}.
                @else
                    {{ __('ومفيش ولا نبضة اتسجلت من الأساس.') }}
                @endif
                {{ __('لازم يكون في سطر كرون على السيرفر بينادي') }}
                <span class="u-mono u-ltr">php artisan schedule:run</span> {{ __('كل دقيقة.') }}
            </x-alert>
        @endunless

        @if ($unseeded !== [])
            <div class="note">
                {{ __('فيه مهام في الكود ومالهاش صفوف، فمش بتشتغل ومش ظاهرة تحت:') }}
                <span class="u-mono u-ltr">{{ implode(__('، '), $unseeded) }}</span> {{ __('— شغّل') }} <span class="u-mono u-ltr">php artisan db:seed --class=ScheduledTaskSeeder --force</span>.
            </div>
        @endif

        <div class="stack">
            @foreach ($tasks as $task)
                @include('admin.scheduled-tasks.partials._task', ['task' => $task])
            @endforeach
        </div>

        <div class="note">
            <span class="u-mono u-ltr">php artisan schedule:audit</span> {{ __('بيقارن الصفوف دي بالقايمة اللي في الكود ·') }}
            <span class="u-mono u-ltr">php artisan schedule:list</span> {{ __('بيوري اللي لارافيل مسجّله فعلاً.') }}
        </div>
    </div>
@endsection
