@extends('layouts.install')

@section('title', __('إنهاء التنصيب'))

@section('content')
    <div class="form-stack">
        <x-form-errors />

        <x-alert>
            {{ __('ستنفّذ الخطوة الأخيرة ما يلي:') }}
            <ul class="alert__list">
                <li>{{ __('توليد مفتاح تشفير جديد خاص بهذا التثبيت.') }}</li>
                <li>{{ __('تعطيل وضع التطوير (') }}<span class="u-mono">APP_DEBUG=false</span>).</li>
                <li>{{ __('كتابة') }} <span class="u-mono">storage/installed.lock</span> {{ __('— وبعدها ستُرجع صفحة التثبيت الخطأ 404.') }}</li>
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
