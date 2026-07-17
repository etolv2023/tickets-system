@extends('layouts.app')

@section('title', 'تعديل ' . $company->name)

@section('content')
    <div class="page page--narrow">
        <div class="page__head">
            <div>
                <h1 class="page-title">تعديل «{{ $company->name }}»</h1>
            </div>
            <div class="page__actions">
                <form method="POST" action="{{ route('admin.companies.destroy', $company) }}"
                      onsubmit="return confirm('متأكد إنك عايز تحذف «{{ $company->name }}»؟')">
                    @csrf
                    @method('DELETE')
                    <x-button variant="ghost" size="sm">حذف الشركة</x-button>
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
