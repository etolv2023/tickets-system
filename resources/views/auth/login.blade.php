@extends('layouts.auth')

@section('title', __('تسجيل الدخول'))
@section('foot', __('إذا نسيت كلمة المرور، فتواصل مع مدير النظام.'))

@section('content')
    <x-card>
        <div class="form-stack">
            <div class="auth__head">
                <h1 class="auth__title">{{ __('تسجيل الدخول') }}</h1>
                <p class="auth__sub">{{ __('سجّل الدخول إلى حسابك للمتابعة.') }}</p>
            </div>

            @if (session('status'))
                <x-alert variant="success">{{ session('status') }}</x-alert>
            @endif

            <form method="POST" action="{{ route('login') }}" class="form-stack">
                @csrf

                <x-field name="email" :label="__('البريد الإلكتروني')" type="email" required autofocus autocomplete="username" />

                <x-field name="password" :label="__('كلمة المرور')" type="password" required autocomplete="current-password" />

                <x-button variant="primary" block>{{ __('دخول') }}</x-button>
            </form>
        </div>
    </x-card>
@endsection
