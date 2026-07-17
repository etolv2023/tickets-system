@extends('layouts.app')

@section('title', 'مستخدم جديد')

@section('content')
    <div class="page page--narrow">
        <div class="page__head">
            <h1 class="page-title">مستخدم جديد</h1>
        </div>

        <form method="POST" action="{{ route('admin.users.store') }}">
            @csrf
            @include('admin.users.partials._form', ['user' => null])
        </form>
    </div>
@endsection
