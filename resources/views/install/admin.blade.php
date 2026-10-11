@extends('layouts.install')

@section('title', __('حساب مدير النظام'))

@section('content')
    <div class="form-stack">
        @if ($output)
            <details>
                <summary class="u-muted">{{ __('ما الذي نُفِّذ في خطوة الجداول') }}</summary>
                <pre class="install__log">{{ $output }}</pre>
            </details>
        @endif

        <x-alert variant="success">
            {{ __('تم إنشاء الجداول. بقي حساب المدير — وهو الحساب الذي ستدخل به أول مرة.') }}
        </x-alert>

        <x-form-errors />

        <form method="POST" action="{{ route('install.admin.store') }}" class="form-stack">
            @csrf

            <x-field name="name" :label="__('الاسم')" required autofocus />

            <x-field name="email" :label="__('البريد الإلكتروني')" type="email" required autocomplete="username" />

            <div class="form-grid">
                <x-field
                    name="password"
                    :label="__('كلمة المرور')"
                    type="password"
                    required
                    :hint="__('10 أحرف على الأقل.')"
                    autocomplete="new-password"
                />
                <x-field
                    name="password_confirmation"
                    :label="__('تأكيد كلمة المرور')"
                    type="password"
                    required
                    autocomplete="new-password"
                />
            </div>

            <div class="install__nav">
                <span></span>
                <x-button variant="primary">{{ __('إنشاء الحساب') }}</x-button>
            </div>
        </form>
    </div>
@endsection
