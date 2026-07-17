@extends('layouts.app')

@section('title', 'دور جديد')

@section('content')
    <div class="page">
        <div class="page__head">
            <div>
                <h1 class="page-title">دور جديد</h1>
                <p class="page-subtitle">اختار أي تركيبة صلاحيات تحتاجها.</p>
            </div>
        </div>

        <form method="POST" action="{{ route('admin.roles.store') }}">
            @csrf
            @include('admin.roles.partials._form', ['role' => null])
        </form>
    </div>
@endsection
