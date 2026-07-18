@extends('layouts.app')

@section('title', 'شركة جديدة')

@section('content')
    <div class="page page--medium">
        <div class="page__head">
            <h1 class="page-title">شركة جديدة</h1>
        </div>

        <form method="POST" action="{{ route('admin.companies.store') }}">
            @csrf
            @include('admin.companies.partials._form', ['company' => null])
        </form>
    </div>
@endsection
