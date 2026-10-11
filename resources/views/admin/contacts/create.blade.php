@extends('layouts.app')

@section('title', __('جهة اتصال جديدة'))

@section('content')
    <div class="page page--medium">
        <div class="page__head">
            <div>
                <h1 class="page-title">{{ __('جهة اتصال جديدة') }}</h1>
                <p class="page-subtitle">{{ __('في «:name»', ['name' => $company->name]) }}</p>
            </div>
        </div>

        <form method="POST" action="{{ route('admin.companies.contacts.store', $company) }}">
            @csrf
            @include('admin.contacts.partials._form', ['contact' => null])
        </form>
    </div>
@endsection
