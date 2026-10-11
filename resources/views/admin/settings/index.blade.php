@extends('layouts.app')

@section('title', __('الإعدادات'))

@section('content')
    <div class="page page--medium">
        <div class="page__head">
            <div>
                <h1 class="page-title">{{ __('الإعدادات') }}</h1>
                <p class="page-subtitle">
                    {{ __('الإعدادات العامة للنظام وساعات الدوام. أصبحت مهلة SLA لكل أولوية في') }}
                    <a href="{{ route('admin.priorities.index') }}">{{ __('شاشة الأولويات') }}</a>.
                </p>
            </div>
        </div>

        @if (session('status'))
            <x-alert variant="success">{{ session('status') }}</x-alert>
        @endif

        <x-form-errors />

        <form method="POST" action="{{ route('admin.settings.update') }}" enctype="multipart/form-data" class="form-stack">
            @csrf
            @method('PUT')

            @include('admin.settings.partials._identity')
            @include('admin.settings.partials._capacity')
            @include('admin.settings.partials._points')
            @include('admin.settings.partials._notifications')

            <div class="form-actions">
                <x-button variant="primary">{{ __('حفظ الإعدادات') }}</x-button>
            </div>
        </form>
    </div>
@endsection
