@extends('layouts.install')

@section('title', 'إنهاء التنصيب')

@section('content')
    <div class="form-stack">
        <x-form-errors />

        <x-alert>
            آخر خطوة هتعمل الآتي:
            <ul class="alert__list">
                <li>تولّد مفتاح تشفير جديد خاص بالتنصيب ده.</li>
                <li>تقفل وضع التطوير (<span class="u-mono">APP_DEBUG=false</span>).</li>
                <li>تكتب <span class="u-mono">storage/installed.lock</span> — وبعدها صفحة التنصيب هترجع 404.</li>
            </ul>
        </x-alert>

        <div class="install__nav">
            <span></span>
            <form method="POST" action="{{ route('install.finish.complete') }}">
                @csrf
                <x-button variant="primary">إنهاء التنصيب والدخول</x-button>
            </form>
        </div>
    </div>
@endsection
