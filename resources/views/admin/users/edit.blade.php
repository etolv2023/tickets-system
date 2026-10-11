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

        <x-card :title="__('كلمة المرور')">
            <div class="row row--between row--wrap">
                <div>
                    <p>{{ __('تولّد إعادة التعيين كلمة مرور عشوائية وتُلزم المستخدم بتغييرها عند أول دخول.') }}</p>
                    <p class="field__hint">{{ __('لا يمكن الاطلاع على كلمة مروره الحالية — فهي مخزّنة مشفّرة.') }}</p>
                </div>
                <form method="POST" action="{{ route('admin.users.reset-password', $user) }}"
                      onsubmit="return confirm({{ Js::from(__('ستُولَّد كلمة مرور جديدة لـ «:name» وستتوقف كلمة المرور القديمة. هل تريد المتابعة؟', ['name' => $user->name])) }})">
                    @csrf
                    <x-button variant="secondary">{{ __('إعادة تعيين كلمة المرور') }}</x-button>
                </form>
            </div>
        </x-card>
    </div>
@endsection
