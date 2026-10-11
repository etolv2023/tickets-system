@extends('layouts.install')

@section('title', __('بيانات قاعدة البيانات'))

@section('content')
    <div class="form-stack">
        <x-alert>
            {{ __('هختبر الاتصال قبل ما أكمل. لو قاعدة البيانات مش موجودة هعملها بنفسي.') }}
        </x-alert>

        <x-form-errors />

        <form method="POST" action="{{ route('install.database.store') }}" class="form-stack">
            @csrf

            <div class="form-grid">
                <x-field name="db_host" :label="__('عنوان السيرفر')" :value="$defaults['db_host']" required dir="ltr" />
                <x-field name="db_port" :label="__('البورت')" :value="$defaults['db_port']" required dir="ltr" />
            </div>

            <x-field
                name="db_database"
                :label="__('اسم قاعدة البيانات')"
                :value="$defaults['db_database']"
                required
                dir="ltr"
                :hint="__('حروف إنجليزية وأرقام و _ بس. لو مش موجودة هتتعمل.')"
            />

            <div class="form-grid">
                <x-field name="db_username" :label="__('اسم المستخدم')" :value="$defaults['db_username']" required dir="ltr" />
                <x-field name="db_password" :label="__('كلمة السر')" type="password" :hint="__('سيبها فاضية لو مفيش كلمة سر.')" />
            </div>

            <div class="install__nav">
                <x-button variant="ghost" :href="route('install.requirements')">{{ __('رجوع') }}</x-button>
                <x-button variant="primary">{{ __('اختبار الاتصال والاستمرار') }}</x-button>
            </div>
        </form>
    </div>
@endsection
