@extends('layouts.app')

@section('title', 'جهة اتصال جديدة')

@section('content')
    <div class="page page--medium">
        <div class="page__head">
            <div>
                <h1 class="page-title">جهة اتصال جديدة</h1>
                <p class="page-subtitle">في «{{ $company->name }}»</p>
            </div>
        </div>

        <form method="POST" action="{{ route('admin.companies.contacts.store', $company) }}">
            @csrf
            @include('admin.contacts.partials._form', ['contact' => null])
        </form>
    </div>
@endsection
