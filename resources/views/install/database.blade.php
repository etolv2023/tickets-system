@extends('layouts.install')

@section('title', __('بيانات قاعدة البيانات'))

@section('content')
    <div class="form-stack">
        <x-alert>
            {{ __('سأختبر الاتصال قبل المتابعة. إذا لم تكن قاعدة البيانات موجودة فسأنشئها.') }}
        </x-alert>

        <x-form-errors />

        <form method="POST" action="{{ route('install.database.store') }}" class="form-stack">
            @csrf

            <div class="form-grid">
                <x-field name="db_host" :label="__('عنوان الخادم')" :value="$defaults['db_host']" required dir="ltr" />
                <x-field name="db_port" :label="__('المنفذ')" :value="$defaults['db_port']" required dir="ltr" />
            </div>

            <x-field
                name="db_database"
                :label="__('اسم قاعدة البيانات')"
                :value="$defaults['db_database']"
                required
                dir="ltr"
                :hint="__('أحرف إنجليزية وأرقام و_ فقط. تُنشأ إن لم تكن موجودة.')"
            />

            <div class="form-grid">
                <x-field name="db_username" :label="__('اسم المستخدم')" :value="$defaults['db_username']" required dir="ltr" />
                <x-field name="db_password" :label="__('كلمة المرور')" type="password" :hint="__('اتركها فارغة إذا لم تكن هناك كلمة مرور.')" />
            </div>

            <div class="install__nav">
                <x-button variant="ghost" :href="route('install.requirements')">{{ __('رجوع') }}</x-button>
                <x-button variant="primary">{{ __('اختبار الاتصال والاستمرار') }}</x-button>
            </div>
        </form>
    </div>
@endsection
