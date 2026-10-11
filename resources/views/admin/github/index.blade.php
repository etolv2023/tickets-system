@extends('layouts.app')

@section('title', __('جيت هب'))

@section('content')
    <div class="page">
        <div class="page__head">
            <div>
                <h1 class="page-title">{{ __('جيت هب') }}</h1>
                <p class="page-subtitle">
                    {{ __('الريبوز اللي النظام بيدوّر فيها على برانشات التذاكر. الاتصال') }} <strong>{{ __('قراءة بس') }}</strong> {{ __('— النظام مبيعملش برانش ولا PR ولا بيحذف حاجة، والتوكن نفسه المفروض يكون') }}
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
                {{ __('التكامل مقفول. حط') }} <span class="u-mono u-ltr">GITHUB_ENABLED=true</span> {{ __('و') }}
                <span class="u-mono u-ltr">GITHUB_TOKEN</span> {{ __('في ملف') }} <span class="u-mono u-ltr">.env</span>{{ __('، وبعدها شغّل') }} <span class="u-mono u-ltr">php artisan github:check</span>.
            </x-alert>
        @endunless

        {{-- Not verified in the browser on purpose: checking the token and its
             scopes is four API calls, and a page with a 300ms budget does not
             make network requests to another company's server while it renders. --}}
        <div class="note">
            {{ __('للتأكد من التوكن وصلاحياته:') }} <span class="u-mono u-ltr">php artisan github:check</span> {{ __('— وده كمان بيحذّرك لو التوكن معاه صلاحية كتابة مش محتاجينها، أو قرّب يخلص.') }}
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
                {{ __('فيه :count PR الهيد برانش بتاعهم مش فيه رقم تذكرة معروف — غالباً غلطة في اسم البرانش.', ['count' => $unmatchedPulls]) }}
            </div>
        @endif

        @include('admin.github.partials._new-repo')
    </div>
@endsection
