@extends('layouts.app')

@section('title', 'تعديل ' . $role->name_ar)

@section('content')
    <div class="page">
        <div class="page__head">
            <div>
                <h1 class="page-title">تعديل «{{ $role->name_ar }}»</h1>
                <p class="page-subtitle">تسري التغييرات فورًا على جميع المستخدمين المرتبطين بهذا الدور.</p>
            </div>
        </div>

        <form method="POST" action="{{ route('admin.roles.update', $role) }}">
            @csrf
            @method('PUT')
            @include('admin.roles.partials._form')
        </form>
    </div>
@endsection
