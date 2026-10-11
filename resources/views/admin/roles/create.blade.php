@extends('layouts.app')

@section('title', __('دور جديد'))

@section('content')
    <div class="page">
        <div class="page__head">
            <div>
                <h1 class="page-title">{{ __('دور جديد') }}</h1>
                <p class="page-subtitle">{{ __('اختر أي مجموعة صلاحيات تحتاج إليها.') }}</p>
            </div>
        </div>

        <form method="POST" action="{{ route('admin.roles.store') }}">
            @csrf
            @include('admin.roles.partials._form', ['role' => null])
        </form>
    </div>
@endsection
