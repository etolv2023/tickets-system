@extends('layouts.install')

@section('title', __('إنشاء الجداول'))

@section('content')
    <div class="form-stack">
        <x-form-errors />

        @if ($alreadyRun)
            <x-alert variant="success">
                {{ __('الجداول موجودة بالفعل. تقدر تكمل، أو تشغّل الخطوة تاني لو ضفت حاجة ناقصة.') }}
            </x-alert>
        @else
            <x-alert>
                {{ __('هعمل جداول النظام، وأزرع الأدوار والصلاحيات والإعدادات الافتراضية.') }}
            </x-alert>
        @endif

        <div class="install__nav">
            <x-button variant="ghost" :href="route('install.database')">{{ __('رجوع') }}</x-button>

            <div class="row">
                @if ($alreadyRun)
                    <x-button variant="secondary" :href="route('install.admin')">{{ __('تخطي') }}</x-button>
                @endif

                <form method="POST" action="{{ route('install.migrate.run') }}">
                    @csrf
                    <x-button variant="primary">{{ __('إنشاء الجداول') }}</x-button>
                </form>
            </div>
        </div>
    </div>
@endsection
