@extends('layouts.auth')

@section('title', 'بوابة العميل')
@section('foot', 'اطلب رقم التذكرة والباسورد من الشخص الي فتحلك التذكرة.')

@section('content')
    <x-card>
        <div class="form-stack">
            <div class="auth__head">
                <h1 class="auth__title">تابع تذكرتك</h1>
                <p class="auth__sub">اكتب رقم التذكرة والباسورد اللي وصلوك.</p>
            </div>

            <x-form-errors />

            <form method="POST" action="{{ route('portal.verify') }}" class="form-stack">
                @csrf

                {{-- Pre-filled from the link but always visible/editable — never
                     a hidden field, even when the number came from the URL. --}}
                <x-field name="ticket_number" label="رقم التذكرة" required
                         :value="old('ticket_number', $ticketNumber)" />

                <x-field name="password" label="الباسورد" type="password" required />

                <x-button variant="primary" block>ادخل</x-button>
            </form>
        </div>
    </x-card>
@endsection
