{{-- Every error screen. Laravel's own templates are English and hard-coded
     lang="en" / left-to-right, so a 404 broke out of the UI language. These sit
     on the login layout: no nav to depend on, but the language and theme
     controls are still there. --}}
@extends('layouts.auth')

@section('title', $__env->yieldContent('heading'))

@section('content')
    <x-card>
        <div class="form-stack">
            <div class="auth__head">
                <p class="error-page__code">@yield('code')</p>
                <h1 class="auth__title">@yield('heading')</h1>
                <p class="auth__sub">@yield('message')</p>
            </div>

            <x-button variant="primary" block :href="url('/')">{{ __('العودة إلى الصفحة الرئيسية') }}</x-button>
        </div>
    </x-card>
@endsection
