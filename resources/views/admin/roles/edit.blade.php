@extends('layouts.app')

@section('title', 'تعديل ' . $role->name_ar)

@section('content')
    <div class="page">
        <div class="page__head">
            <div>
                <h1 class="page-title">تعديل «{{ $role->name_ar }}»</h1>
                <p class="page-subtitle">أي تغيير هنا بيسري فوراً على كل مستخدم على الدور ده.</p>
            </div>
        </div>

        <form method="POST" action="{{ route('admin.roles.update', $role) }}">
            @csrf
            @method('PUT')
            @include('admin.roles.partials._form')
        </form>
    </div>
@endsection
