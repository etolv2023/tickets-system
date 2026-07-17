@extends('layouts.auth')

@section('title', 'تسجيل الدخول')
@section('foot', 'لو نسيت كلمة السر، كلّم مدير النظام.')

@section('content')
    <x-card>
        <div class="form-stack">
            <div class="auth__head">
                <h1 class="auth__title">تسجيل الدخول</h1>
                <p class="auth__sub">ادخل بحسابك عشان تكمل.</p>
            </div>

            @if (session('status'))
                <x-alert variant="success">{{ session('status') }}</x-alert>
            @endif

            <form method="POST" action="{{ route('login') }}" class="form-stack">
                @csrf

                <x-field name="email" label="الإيميل" type="email" required autofocus autocomplete="username" />

                <x-field name="password" label="كلمة السر" type="password" required autocomplete="current-password" />

                <x-button variant="primary" block>دخول</x-button>
            </form>
        </div>
    </x-card>
@endsection
