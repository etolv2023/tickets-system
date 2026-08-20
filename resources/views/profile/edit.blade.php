@extends('layouts.app')

@section('title', 'حسابي')

@section('content')
    <div class="page page--narrow">
        <div class="page__head">
            <div>
                <h1 class="page-title">حسابي</h1>
                <p class="page-subtitle">
                    البيانات اللي انت بس اللي تعرفها. اسمك ودورك وسعتك اليومية
                    بيظبطهم الأدمن من إدارة المستخدمين.
                </p>
            </div>
        </div>

        @if (session('status'))
            <x-alert variant="success">{{ session('status') }}</x-alert>
        @endif

        <x-form-errors />

        <x-card title="ديسكورد">
            <form method="POST" action="{{ route('profile.update') }}" class="form-grid">
                @csrf
                @method('PUT')

                <x-field name="discord_user_id" label="الـ Discord User ID"
                         :value="$user->discord_user_id"
                         inputmode="numeric" autocomplete="off"
                         placeholder="709211234567890123"
                         hint="لما اكسبشن يتوزّع عليك، الرسالة على ديسكورد هتعملك منشن حقيقي بيوصلك إشعار. سيبها فاضية ولو عايز — هيتكتب اسمك كنص عادي من غير إشعار." />

                <div class="form-actions">
                    <x-button variant="primary">احفظ</x-button>
                </div>
            </form>

            @include('profile.partials._discord-help')
        </x-card>

        <x-card title="كلمة السر">
            <p class="u-subtle">تغيير كلمة السر في صفحة لوحدها.</p>
            <div class="form-actions">
                <a href="{{ route('password.change') }}" class="btn btn--secondary">غيّر كلمة السر</a>
            </div>
        </x-card>
    </div>
@endsection
