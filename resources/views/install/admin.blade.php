@extends('layouts.install')

@section('title', __('حساب مدير النظام'))

@section('content')
    <div class="form-stack">
        @if ($output)
            <details>
                <summary class="u-muted">{{ __('اتعمل إيه في خطوة الجداول') }}</summary>
                <pre class="install__log">{{ $output }}</pre>
            </details>
        @endif

        <x-alert variant="success">
            {{ __('الجداول اتعملت. فاضل حساب المدير — ده الحساب الي هتدخل بيه أول مرة.') }}
        </x-alert>

        <x-form-errors />

        <form method="POST" action="{{ route('install.admin.store') }}" class="form-stack">
            @csrf

            <x-field name="name" :label="__('الاسم')" required autofocus />

            <x-field name="email" :label="__('الإيميل')" type="email" required autocomplete="username" />

            <div class="form-grid">
                <x-field
                    name="password"
                    :label="__('كلمة السر')"
                    type="password"
                    required
                    :hint="__('10 حروف على الأقل.')"
                    autocomplete="new-password"
                />
                <x-field
                    name="password_confirmation"
                    :label="__('تأكيد كلمة السر')"
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
