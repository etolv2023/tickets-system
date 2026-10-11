@extends('layouts.app')

@section('title', __('تعديل :name', ['name' => $contact->name]))

@section('content')
    <div class="page page--medium">
        <div class="page__head">
            <div>
                <h1 class="page-title">{{ __('تعديل «:name»', ['name' => $contact->name]) }}</h1>
                <p class="page-subtitle">{{ __('في «:name»', ['name' => $company->name]) }}</p>
            </div>
        </div>

        <form method="POST" action="{{ route('admin.companies.contacts.update', [$company, $contact]) }}">
            @csrf
            @method('PUT')
            @include('admin.contacts.partials._form')
        </form>
    </div>
@endsection
