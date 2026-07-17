@extends('layouts.app')

@section('title', 'الإعدادات')

@section('content')
    <div class="page page--narrow">
        <div class="page__head">
            <div>
                <h1 class="page-title">الإعدادات</h1>
                <p class="page-subtitle">إعدادات النظام العامة والمهل الزمنية.</p>
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
            @include('admin.settings.partials._sla')
            @include('admin.settings.partials._capacity')
            @include('admin.settings.partials._notifications')

            <div class="form-actions">
                <x-button variant="primary">حفظ الإعدادات</x-button>
            </div>
        </form>
    </div>
@endsection
