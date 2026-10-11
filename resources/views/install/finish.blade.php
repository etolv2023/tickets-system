@extends('layouts.install')

@section('title', __('إنهاء التنصيب'))

@section('content')
    <div class="form-stack">
        <x-form-errors />

        <x-alert>
            {{ __('آخر خطوة هتعمل الآتي:') }}
            <ul class="alert__list">
                <li>{{ __('تولّد مفتاح تشفير جديد خاص بالتنصيب ده.') }}</li>
                <li>{{ __('تقفل وضع التطوير (') }}<span class="u-mono">APP_DEBUG=false</span>).</li>
                <li>{{ __('تكتب') }} <span class="u-mono">storage/installed.lock</span> {{ __('— وبعدها صفحة التنصيب هترجع 404.') }}</li>
            </ul>
        </x-alert>

        <div class="install__nav">
            <span></span>
            <form method="POST" action="{{ route('install.finish.complete') }}">
                @csrf
                <x-button variant="primary">{{ __('إنهاء التنصيب والدخول') }}</x-button>
            </form>
        </div>
    </div>
@endsection
