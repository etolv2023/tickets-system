@extends('layouts.app')

@section('title', __('تعديل :name', ['name' => $role->label()]))

@section('content')
    <div class="page">
        <div class="page__head">
            <div>
                <h1 class="page-title">{{ __('تعديل «:name»', ['name' => $role->label()]) }}</h1>
                <p class="page-subtitle">{{ __('تسري التغييرات فورًا على جميع المستخدمين المرتبطين بهذا الدور.') }}</p>
            </div>
        </div>

        <form method="POST" action="{{ route('admin.roles.update', $role) }}">
            @csrf
            @method('PUT')
            @include('admin.roles.partials._form')
        </form>
    </div>
@endsection
