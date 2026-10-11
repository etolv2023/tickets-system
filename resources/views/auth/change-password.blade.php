@extends('layouts.auth')

@section('title', __('تغيير كلمة المرور'))

@section('content')
    <x-card>
        <div class="form-stack">
            <div class="auth__head">
                <h1 class="auth__title">{{ __('تغيير كلمة المرور') }}</h1>
                @if ($forced)
                    <p class="auth__sub">{{ __('أُنشئ حسابك بكلمة مرور مؤقتة. يجب تغييرها قبل المتابعة.') }}</p>
                @else
                    <p class="auth__sub">{{ __('اختر كلمة مرور جديدة — 10 أحرف على الأقل.') }}</p>
                @endif
            </div>

            <x-form-errors />

            <form method="POST" action="{{ route('password.change.store') }}" class="form-stack">
                @csrf
                @method('PUT')

                <x-field
                    name="current_password"
                    :label="__('كلمة المرور الحالية')"
                    type="password"
                    required
                    autofocus
                    autocomplete="current-password"
                />

                <x-field
                    name="password"
                    :label="__('كلمة المرور الجديدة')"
                    type="password"
                    required
                    :hint="__('10 أحرف على الأقل.')"
                    autocomplete="new-password"
                />

                <x-field
                    name="password_confirmation"
                    :label="__('تأكيد كلمة المرور الجديدة')"
                    type="password"
                    required
                    autocomplete="new-password"
                />

                <x-button variant="primary" block>{{ __('حفظ كلمة المرور') }}</x-button>
            </form>
        </div>
    </x-card>
@endsection
