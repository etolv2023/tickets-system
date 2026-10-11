@extends('layouts.app')

@section('title', __('تعديل :name', ['name' => $user->name]))

@section('content')
    <div class="page page--medium">
        <div class="page__head">
            <div>
                <h1 class="page-title">{{ __('تعديل «:name»', ['name' => $user->name]) }}</h1>
                <p class="page-subtitle u-ltr">{{ $user->email }}</p>
            </div>
        </div>

        @if (session('generated_password'))
            @include('admin.users.partials._generated-password')
        @endif

        @if ($errors->any() && $errors->has('deactivate'))
            <x-alert variant="error">{{ $errors->first('deactivate') }}</x-alert>
        @endif

        <form method="POST" action="{{ route('admin.users.update', $user) }}">
            @csrf
            @method('PUT')
            @include('admin.users.partials._form')
        </form>

        <x-card :title="__('كلمة السر')">
            <div class="row row--between row--wrap">
                <div>
                    <p>{{ __('إعادة تعيين بتولّد كلمة سر عشوائية وتجبره يغيّرها أول دخول.') }}</p>
                    <p class="field__hint">{{ __('مفيش طريقة تشوف كلمة سره الحالية — دي متخزنة مشفّرة.') }}</p>
                </div>
                <form method="POST" action="{{ route('admin.users.reset-password', $user) }}"
                      onsubmit="return confirm({{ Js::from(__('هتتولّد كلمة سر جديدة لـ «:name» وكلمة السر القديمة هتبطل. تمام؟', ['name' => $user->name])) }})">
                    @csrf
                    <x-button variant="secondary">{{ __('إعادة تعيين كلمة السر') }}</x-button>
                </form>
            </div>
        </x-card>
    </div>
@endsection
