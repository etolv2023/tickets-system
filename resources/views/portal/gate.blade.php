@extends('layouts.auth')

@section('title', __('بوابة العميل'))
@section('foot', __('اطلب رقم التذكرة وكلمة المرور من الشخص الذي أنشأ لك التذكرة.'))

@section('content')
    <x-card>
        <div class="form-stack">
            <div class="auth__head">
                <h1 class="auth__title">{{ __('تتبّع تذكرتك') }}</h1>
                <p class="auth__sub">{{ __('أدخل رقم التذكرة وكلمة المرور اللذين وصلاك.') }}</p>
            </div>

            <x-form-errors />

            <form method="POST" action="{{ route('portal.verify') }}" class="form-stack">
                @csrf

                {{-- Pre-filled from the link but always visible/editable — never
                     a hidden field, even when the number came from the URL. --}}
                <x-field name="ticket_number" :label="__('رقم التذكرة')" required
                         :value="old('ticket_number', $ticketNumber)" />

                <x-field name="password" :label="__('كلمة المرور')" type="password" required />

                <x-button variant="primary" block>{{ __('دخول') }}</x-button>
            </form>
        </div>
    </x-card>
@endsection
