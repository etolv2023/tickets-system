@extends('layouts.app')

@section('title', 'تعديل ' . $contact->name)

@section('content')
    <div class="page page--medium">
        <div class="page__head">
            <div>
                <h1 class="page-title">تعديل «{{ $contact->name }}»</h1>
                <p class="page-subtitle">في «{{ $company->name }}»</p>
            </div>
        </div>

        <form method="POST" action="{{ route('admin.companies.contacts.update', [$company, $contact]) }}">
            @csrf
            @method('PUT')
            @include('admin.contacts.partials._form')
        </form>
    </div>
@endsection
