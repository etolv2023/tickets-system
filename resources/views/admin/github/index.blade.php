@extends('layouts.app')

@section('title', __('GitHub'))

@section('content')
    <div class="page">
        <div class="page__head">
            <div>
                <h1 class="page-title">{{ __('GitHub') }}</h1>
                <p class="page-subtitle">
                    {{ __('المستودعات التي يبحث فيها النظام عن فروع التذاكر. الاتصال') }} <strong>{{ __('قراءة فقط') }}</strong> {{ __('— النظام لا ينشئ فروعًا ولا طلبات دمج ولا يحذف أي شيء، ويجب أن يكون رمز الوصول نفسه') }}
                    <span class="u-mono u-ltr">Contents: Read-only</span> +
                    <span class="u-mono u-ltr">Pull requests: Read-only</span>.
                </p>
            </div>
        </div>

        @if (session('status'))
            <x-alert variant="success">{{ session('status') }}</x-alert>
        @endif

        <x-form-errors />

        @unless ($connected)
            <x-alert variant="error">
                {{ __('التكامل معطّل. اضبط') }} <span class="u-mono u-ltr">GITHUB_ENABLED=true</span> {{ __('و') }}
                <span class="u-mono u-ltr">GITHUB_TOKEN</span> {{ __('في الملف') }} <span class="u-mono u-ltr">.env</span>{{ __('، ثم شغّل') }} <span class="u-mono u-ltr">php artisan github:check</span>.
            </x-alert>
        @endunless

        {{-- Not verified in the browser on purpose: checking the token and its
             scopes is four API calls, and a page with a 300ms budget does not
             make network requests to another company's server while it renders. --}}
        <div class="note">
            {{ __('للتحقق من رمز الوصول وصلاحياته:') }} <span class="u-mono u-ltr">php artisan github:check</span> {{ __('— ويحذّرك أيضًا إذا كان لرمز الوصول صلاحية كتابة غير مطلوبة، أو اقترب انتهاؤه.') }}
        </div>

        {{-- Cards, not a table: six editable fields per repository do not fit a
             row, and there are four rows. See _repo-card for the full reasoning. --}}
        <div class="stack">
            @foreach ($repositories as $repository)
                @include('admin.github.partials._repo-card', ['repository' => $repository])
            @endforeach
        </div>

        @if ($unmatchedPulls > 0)
            <div class="note">
                {{ __('يوجد :count طلب دمج لا يحتوي فرعها الرئيسي على رقم تذكرة معروف — غالبًا بسبب خطأ في اسم الفرع.', ['count' => $unmatchedPulls]) }}
            </div>
        @endif

        @include('admin.github.partials._new-repo')
    </div>
@endsection
