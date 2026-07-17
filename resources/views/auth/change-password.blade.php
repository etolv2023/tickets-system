@extends('layouts.auth')

@section('title', 'تغيير كلمة السر')

@section('content')
    <x-card>
        <div class="form-stack">
            <div class="auth__head">
                <h1 class="auth__title">تغيير كلمة السر</h1>
                @if ($forced)
                    <p class="auth__sub">حسابك اتعمل بكلمة سر مؤقتة. لازم تغيّرها قبل ما تكمل.</p>
                @else
                    <p class="auth__sub">اختار كلمة سر جديدة — 10 حروف على الأقل.</p>
                @endif
            </div>

            <x-form-errors />

            <form method="POST" action="{{ route('password.change.store') }}" class="form-stack">
                @csrf
                @method('PUT')

                <x-field
                    name="current_password"
                    label="كلمة السر الحالية"
                    type="password"
                    required
                    autofocus
                    autocomplete="current-password"
                />

                <x-field
                    name="password"
                    label="كلمة السر الجديدة"
                    type="password"
                    required
                    hint="10 حروف على الأقل."
                    autocomplete="new-password"
                />

                <x-field
                    name="password_confirmation"
                    label="تأكيد كلمة السر الجديدة"
                    type="password"
                    required
                    autocomplete="new-password"
                />

                <x-button variant="primary" block>حفظ كلمة السر</x-button>
            </form>
        </div>
    </x-card>
@endsection
