@extends('layouts.auth')

@section('title', __('تغيير كلمة السر'))

@section('content')
    <x-card>
        <div class="form-stack">
            <div class="auth__head">
                <h1 class="auth__title">{{ __('تغيير كلمة السر') }}</h1>
                @if ($forced)
                    <p class="auth__sub">{{ __('حسابك اتعمل بكلمة سر مؤقتة. لازم تغيّرها قبل ما تكمل.') }}</p>
                @else
                    <p class="auth__sub">{{ __('اختار كلمة سر جديدة — 10 حروف على الأقل.') }}</p>
                @endif
            </div>

            <x-form-errors />

            <form method="POST" action="{{ route('password.change.store') }}" class="form-stack">
                @csrf
                @method('PUT')

                <x-field
                    name="current_password"
                    :label="__('كلمة السر الحالية')"
                    type="password"
                    required
                    autofocus
                    autocomplete="current-password"
                />

                <x-field
                    name="password"
                    :label="__('كلمة السر الجديدة')"
                    type="password"
                    required
                    :hint="__('10 حروف على الأقل.')"
                    autocomplete="new-password"
                />

                <x-field
                    name="password_confirmation"
                    :label="__('تأكيد كلمة السر الجديدة')"
                    type="password"
                    required
                    autocomplete="new-password"
                />

                <x-button variant="primary" block>{{ __('حفظ كلمة السر') }}</x-button>
            </form>
        </div>
    </x-card>
@endsection
