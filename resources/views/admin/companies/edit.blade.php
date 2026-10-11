@extends('layouts.app')

@section('title', __('تعديل :name', ['name' => $company->name]))

@section('content')
    <div class="page page--medium">
        <div class="page__head">
            <div>
                <h1 class="page-title">{{ __('تعديل «:name»', ['name' => $company->name]) }}</h1>
            </div>
            <div class="page__actions">
                <form method="POST" action="{{ route('admin.companies.destroy', $company) }}"
                      onsubmit="return confirm({{ Js::from(__('هل أنت متأكد من حذف «:name»؟', ['name' => $company->name])) }})">
                    @csrf
                    @method('DELETE')
                    <x-button variant="ghost" size="sm">{{ __('حذف الشركة') }}</x-button>
                </form>
            </div>
        </div>

        <form method="POST" action="{{ route('admin.companies.update', $company) }}">
            @csrf
            @method('PUT')
            @include('admin.companies.partials._form')
        </form>
    </div>
@endsection
