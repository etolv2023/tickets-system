@extends('layouts.app')

@section('title', __('حسابي'))

@section('content')
    <div class="page page--narrow">
        <div class="page__head">
            <div>
                <h1 class="page-title">{{ __('حسابي') }}</h1>
                <p class="page-subtitle">
                    {{ __('بيانات تعرفها أنت فقط. اسمك ودورك وسعتك اليومية يضبطها المسؤول من إدارة المستخدمين.') }}
                </p>
            </div>
        </div>

        @if (session('status'))
            <x-alert variant="success">{{ session('status') }}</x-alert>
        @endif

        <x-form-errors />

        <x-card :title="__('Discord')">
            <form method="POST" action="{{ route('profile.update') }}" class="form-grid">
                @csrf
                @method('PUT')

                <x-field name="discord_user_id" :label="__('معرّف المستخدم في Discord')"
                         :value="$user->discord_user_id"
                         inputmode="numeric" autocomplete="off"
                         placeholder="709211234567890123"
                         :hint="__('عند إسناد استثناء إليك، ستشير إليك رسالة Discord إشارة حقيقية تصلك كإشعار. يمكنك تركها فارغة — وسيُكتب اسمك نصًا عاديًا دون إشعار.')" />

                <div class="form-actions">
                    <x-button variant="primary">{{ __('حفظ') }}</x-button>
                </div>
            </form>

            @include('profile.partials._discord-help')
        </x-card>

        <x-card :title="__('كلمة المرور')">
            <p class="u-subtle">{{ __('تغيير كلمة المرور له صفحة مستقلة.') }}</p>
            <div class="form-actions">
                <a href="{{ route('password.change') }}" class="btn btn--secondary">{{ __('تغيير كلمة المرور') }}</a>
            </div>
        </x-card>
    </div>
@endsection
